<?php

namespace Acquia\Cli\Command\Email;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Exception\ApiErrorException;
use AcquiaCloudApi\Response\SubscriptionResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConfigurePlatformEmailCommand.
 */
class ConfigurePlatformEmailCommand extends CommandBase {

  protected static $defaultName = 'email:configure';

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private $checklist;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Configure Platform email for one or more applications')
      ->addArgument('subscriptionUuid', InputArgument::OPTIONAL)
      ->addOption('domain', NULL, InputOption::VALUE_REQUIRED)
      ->setAliases(['ec']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->io->writeln('Welcome to Platform Email setup! This script will walk you through the whole process setting up Platform Email, all through the command line and using the Cloud API!');
    $this->io->writeln('Before getting started, make sure you have the following: ');

    $this->checklist = new Checklist($output);
    $this->checklist->addItem('the domain name you are registering');
    $this->checklist->completePreviousItem();
    $this->checklist->addItem('the subscription where the domain will be registered');
    $this->checklist->completePreviousItem();
    $this->checklist->addItem('the application or applications where the domain will be associated');
    $this->checklist->completePreviousItem();
    $this->checklist->addItem('the environment or environments for the above applications where Platform Email will be enabled');
    $this->checklist->completePreviousItem();
    $base_domain = $this->determineDomain($input);
    $client = $this->cloudApiClientService->getClient();
    $subscription = $this->determineCloudSubscription();
    $response = $client->request('post', "/subscriptions/{$subscription->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ]);

    $domain_uuid = $this->fetchDomainUuid($client, $subscription, $base_domain);

    $this->io->success([
      "Great! You've registered the domain {$base_domain} to subscription {$subscription->name}.",
      "We will create a text file with the DNS records for your newly registered domain",
      "Provide these records to your DNS provider",
      "After you've done this, please continue to domain verification."
    ]);
    $file_format = $this->io->choice('Would you like your DNS records in JSON or YAML format?', ['YAML', 'JSON'], 'YAML');
    $this->createDnsText($client, $subscription, $domain_uuid, $file_format);
    $continue = $this->io->confirm('Have you finished providing the DNS records to your DNS provider?');
    if (!$continue) {
      $this->io->info("Make sure to give these records to your DNS provider, then rerun this script with the domain that you just registered.");
      return 1;
    }

    // Allow for as many reverification tries as needed.
    while (!$this->pollDomainRegistrationsUntilSuccess($subscription, $domain_uuid, $this->output)) {
      $retry_verification = $this->io->confirm('Would you like to re-check domain verification?');
      if (!$retry_verification) {
        $this->io->writeln('Please check your DNS records with your DNS provider and try again by rerunning this script with the domain that you just registered.');
        return 1;
      }
    }

    $this->io->success("The next step is associating your verified domain with an application (or applications) in the subscription where your domain has been registered.");

    if (!$this->addDomainToSubscriptionApplications($client, $subscription, $base_domain, $domain_uuid)) {
      $this->io->error('Something went wrong with associating your application(s) or enabling your environment(s). Please try again.');
      return 1;
    };

    $this->io->success("You're all set to start using Platform Email!");

    return 0;
  }

  /**
   * Associates a domain with an application or applications,
   * then enables Platform Email for an environment or environments
   * of the above applications.
   *
   * @param Client $client
   * @param SubscriptionResponse $subscription
   * @param string $base_domain
   * @param string $domain_uuid
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function addDomainToSubscriptionApplications(Client $client, SubscriptionResponse $subscription, string $base_domain, string $domain_uuid) {
    $applications_resource = new Applications($client);
    $applications = $applications_resource->getAll();
    $subscription_applications = [];
    foreach ($applications as $application) {
      if ($application->subscription->uuid === $subscription->uuid) {
        $subscription_applications[] = $application;
      }
    }
    if (count($subscription_applications) === 0) {
      throw new AcquiaCliException("You do not have access to any applications on the {$subscription->name} subscription");
    }
    elseif (count($subscription_applications) === 1) {
      $applications = $subscription_applications;
      $this->io->info("You have one application, {$applications[0]->name}, in this subscription.");
    }
    else {
      $applications = $this->promptChooseFromObjectsOrArrays($subscription_applications, 'uuid', 'name', "What are the applications you'd like to associate this domain with? You may enter multiple separated by a comma.", TRUE);
    }

    $environments_resource = new Environments($client);
    foreach ($applications as $application) {
      try {
        $response = $client->request('post', "/applications/{$application->uuid}/email/domains/{$domain_uuid}/actions/associate");
        $this->io->success("Domain $base_domain has been associated with Application {$application->name}");
      } catch (ApiErrorException $e) {
        // Shows a warning and allows user to continue if the domain has already been associated.
        // For any other error from the API, the setup will exit.
        if (strpos($e, 'is already associated with this application') === FALSE) {
          $this->io->error($e->getMessage());
          return FALSE;
        }
        else {
          $this->io->warning($e->getMessage());
        }
      }

      $application_environments = $environments_resource->getAll($application->uuid);
      $envs = $this->promptChooseFromObjectsOrArrays($application_environments, 'uuid', 'label', "What are the environments of {$application->name} that you'd like to enable email for? You may enter multiple separated by a comma.", TRUE);
      foreach ($envs as $env) {
        try {
          $response = $client->request('post', "/environments/{$env->uuid}/email/actions/enable");
          $this->io->success("Platform Email has been enabled for environment {$env->label} for application {$application->name}");
        }
        catch (ApiErrorException $e) {
          // Shows a warning and allows user to continue if Platform Email has already been enabled for the environment.
          // For any other error from the API, the setup will exit.
          if (strpos($e, 'is already enabled on this environment') === FALSE) {
            $this->io->error($e->getMessage());
            return FALSE;
          }
          else {
            $this->io->warning($env->label . ' - ' . $e->getMessage());
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Validates the URL entered as the base domain name.
   *
   * @param string $url
   *
   * @return string
   * @throws \Symfony\Component\Validator\Exception\ValidatorException
   */
  public static function validateUrl(string $url): string {
    $violations = Validation::createValidator()->validate($url, [
      new NotBlank(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    return $url;
  }

  /**
   * Retrieves a domain registration UUID given the domain name.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   * @param SubscriptionResponse $subscription
   * @param string $base_domain
   *
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function fetchDomainUuid(Client $client, $subscription, $base_domain) {
    $domains_response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains");
    foreach ($domains_response as $domain) {
      if ($domain->domain_name === $base_domain) {
        return $domain->uuid;
      }
    }
    throw new AcquiaCliException("Could not find domain $base_domain");
  }

  /**
   * Creates a TXT file, either in JSON or YAML format,
   * of the DNS records needed to complete Platform Email setup.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   * @param SubscriptionResponse $subscription
   * @param string $domain_uuid
   * @param string $file_format
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function createDnsText(Client $client, $subscription, $domain_uuid, $file_format): void {
    $domain_registration_response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
    if (!isset($domain_registration_response->dns_records)) {
      throw new AcquiaCliException('Could not retrieve DNS records for this domain. Please try again by rerunning this script with the domain that you just registered.');
    }
    $records = [];
    $this->localMachineHelper->getFilesystem()->remove('dns-records.txt');
    if ($file_format === 'JSON') {
      foreach ($domain_registration_response->dns_records as $record) {
        unset($record->health);
        $records[] = $record;
      }
      $this->logger->debug(json_encode($records));
      $this->localMachineHelper->getFilesystem()
            ->dumpFile('dns-records.txt', json_encode($records, JSON_PRETTY_PRINT));
    }
    else {
      foreach ($domain_registration_response->dns_records as $record) {
        unset($record->health);
        $records[] = ['type' => $record->type, 'name' => $record->name, 'value' => $record->value];
      }
      $this->logger->debug(json_encode($records));
      $this->localMachineHelper->getFilesystem()
            ->dumpFile('dns-records.txt', Yaml::dump($records));
    }

  }

  /**
   * Polls the Cloud Platform until the registered domain is verified
   * environment.
   *
   * @param \AcquiaCloudApi\Response\SubscriptionResponse $subscription
   * @param string $domain_uuid
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return bool
   */
  protected function pollDomainRegistrationsUntilSuccess(
    SubscriptionResponse $subscription,
    string $domain_uuid,
    OutputInterface $output
  ): bool {
    $client = $this->cloudApiClientService->getClient();
    try {
      $response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
      if (isset($response->health) && $response->health->code === "200") {
        $output->writeln("\n<info>Your domain is ready for use!</info>\n");
        return TRUE;
      }
      elseif (isset($response->health) && substr($response->health->code, 0, 1) == "4") {
        $this->io->error($response->health->details);
        $reverify = $this->io->confirm('Would you like to refresh?');
        if ($reverify) {
          $refresh_response = $client->request('post', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}/actions/verify");
          $this->io->info('Refreshing...');
        }
        return FALSE;
      }
      else {
        $this->io->info("Verification pending...");
        $this->logger->debug(json_encode($response));
        return FALSE;
      }
    } catch (AcquiaCliException $exception) {
      // Do nothing. Keep waiting and looping and logging.
      $this->logger->debug($exception->getMessage());
      return FALSE;
    }
  }

  /**
   * Finds, validates, and trims the URL to be used as the base domain
   * for setting up Platform Email.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return array|string|string[]
   */
  protected function determineDomain(InputInterface $input) {
    if ($input->getOption('domain')) {
      $domain = $input->getOption('domain');
    }
    else {
      $domain = $this->io->ask("What's the domain name you'd like to register?", NULL, \Closure::fromCallable([
        $this,
        'validateUrl'
      ]));
    }

    $domain_parts = parse_url($domain);
    if (array_key_exists('host', $domain_parts)) {
      $return = $domain_parts['host'];
    }
    else {
      $return = $domain;
    }
    return str_replace('www.', '', $return);
  }

}
