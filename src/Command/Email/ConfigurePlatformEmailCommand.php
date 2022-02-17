<?php

namespace Acquia\Cli\Command\Email;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\SubscriptionResponse;
use React\EventLoop\Loop;
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
    $this->io->writeln('Welcome to Platform Email setup! This script will walk you through setting up Platform Email, all through the command line and using the Cloud API!');

    $base_domain = $this->determineDomain($input);
    $client = $this->cloudApiClientService->getClient();
    $subscription = $this->determineCloudSubscription();
    $response = $client->request('post', "/subscriptions/{$subscription->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ]);
    // @todo Check response!

    $domain_uuid = $this->fetchDomainUuid($client, $subscription, $base_domain);

    $this->io->success([
      "Great! You've registered the domain {$base_domain} to subscription {$subscription->name}.",
      "We will create a text file with the DNS records for your newly registered domain",
      "Provide these records to your DNS provider",
      "After you've done this, please continue."
    ]);
    $file_format = $this->io->choice('Would you like your output in JSON or YAML format?', ['YAML', 'JSON'], 'YAML');
    $this->createDnsText($client, $subscription, $domain_uuid, $file_format);
    $continue = $this->io->confirm('Have you finished providing the DNS records to your DNS provider?');
    if (!$continue) {
      $this->io->info("Make sure to give these records to your DNS provider, then rerun this script with the domain that you just registered.");
      return 1;
    }

    $this->pollDomainRegistrationsUntilSuccess($subscription, $domain_uuid, $this->output);
    $this->io->success("The next step is associating your verified domain with an application (or applications) in the subscription where your domain has been registered.");

    $this->addDomainToSubscriptionApplications($client, $subscription, $base_domain, $domain_uuid);
    $this->io->success("You're all set to start using Platform Email!");

    return 0;
  }

  /**
   * @param Client $client
   * @param SubscriptionResponse $subscription
   * @param string $base_domain
   * @param string $domain_uuid
   *
   * @return int|void
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
      $this->io->error("You do not have access to any applications on the {$subscription->name} subscription");
      return 1;
    }
    elseif (count($subscription_applications) === 1) {
      $applications = $subscription_applications;
    }
    else {
      $applications = $this->promptChooseFromObjectsOrArrays($subscription_applications, 'uuid', 'name', "What are the applications you'd like to associate this domain with? You may enter multiple separated by a comma.", TRUE);
    }

    $environments_resource = new Environments($client);
    foreach ($applications as $application) {
      $response = $client->request('post', "/applications/{$application->uuid}/email/domains/{$domain_uuid}/actions/associate");
      // @todo Check response!
      $this->io->success("Domain $base_domain has been associated with Application {$application->name}");
      $application_environments = $environments_resource->getAll($application->uuid);
      $envs = $this->promptChooseFromObjectsOrArrays($application_environments, 'uuid', 'label', "What are the environments of {$application->name} that you'd like to enable email for? You may enter multiple separated by a comma.", TRUE);
      foreach ($envs as $env) {
        $response = $client->request('post', "/environments/{$env->uuid}/email/actions/enable");
        // @todo Check response!
        $this->io->success("Platform Email has been enabled for environment {$env->label} for application {$application->name}");
      }
    }
  }

  /**
   * @param string $url
   *
   * @return string
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
   * @param \AcquiaCloudApi\Connector\Client $client
   * @param SubscriptionResponse $subscription
   * @param string $domain_uuid
   */
  protected function createDnsText(Client $client, $subscription, $domain_uuid, $file_format): void {
    $domain_registration_response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
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
   */
  protected function pollDomainRegistrationsUntilSuccess(
    SubscriptionResponse $subscription,
    string $domain_uuid,
    OutputInterface $output
  ): void {
    // Create a loop to periodically poll the Cloud Platform.
    $loop = Loop::get();
    $spinner = LoopHelper::addSpinnerToLoop($loop, 'Waiting for the domains to be configured...', $output);
    $client = $this->cloudApiClientService->getClient();

    // Poll Cloud every 30 seconds.
    $loop->addPeriodicTimer(30, function () use ($output, $loop, $client, $subscription, $domain_uuid, $spinner) {
      try {
        $response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
        if ($response->health->code[0] === "4") {
          $this->io->error($response->health->details);
          $confirm_reverify = $this->io->confirm('Would you like to retry verification?');
          if ($confirm_reverify) {
            $reverify_request = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}/actions/verify");
          }
        }
        if ($response->health->code === "200") {
          LoopHelper::finishSpinner($spinner);
          $loop->stop();
          $output->writeln("\n<info>Your domain is ready for use!</info>\n");
        }
        else {
          $this->logger->debug(json_encode($response));
        }
      } catch (AcquiaCliException $exception) {
        // Do nothing. Keep waiting and looping and logging.
        $this->logger->debug($exception->getMessage());
      }
    });
    $loop->run();
  }

  /**
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
