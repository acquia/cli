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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class ConfigurePlatformEmailCommand.
 */
class ConfigurePlatformEmailCommand extends CommandBase {

  protected static $defaultName = 'email:configure';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('')
      ->addArgument('subscriptionUuid', InputArgument::OPTIONAL)
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
    $domain = $this->io->ask("What's the domain name you'd like to register?", NULL, \Closure::fromCallable([$this, 'validateUrl']));
    $domain_parts = parse_url($domain);
    $base_domain = str_replace('www.', '', $domain_parts['host']);

    $client = $this->cloudApiClientService->getClient();
    $subscription = $this->determineCloudSubscription();
    $response = $client->request('post', "/subscriptions/{$subscription->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ]);

    $domain_uuid = $this->fetchDomainUuid($client, $subscription, $base_domain);
    $this->createDnsText($client, $subscription, $domain_uuid);

    $this->io->success([
      "Great! You've registered the domain {$base_domain} to subscription {$subscription->name}.",
      "We created dns-records.txt",
      "Provide these records to your DNS provider",
      "After you've done this, continue."
    ]);
    $continue = $this->io->confirm('Have you finished providing the DNS records to your DNS provider?');
    if (!$continue) {
      return 1;
    }

    $this->pollDomainRegistrationsUntilSuccess($subscription, $domain_uuid, $this->output);
    $this->io->success("The next step is associating your verified domain with an application (or applications) in the subscription where your domain has been registered.");

    $this->addDomainToSubscriptionApplications($client, $subscription, $domain_uuid);

    return 0;
  }

  /**
   * @param $client
   * @param $subscription
   * @param $domain_uuid
   *
   * @return int|void
   */
  protected function addDomainToSubscriptionApplications($client, $subscription, $domain_uuid) {
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
      $response = $client->request('get', "/applications/{$application->uuid}/email/domains/{$domain_uuid}/actions/associate");
      $this->io->success("Domain $domain_uuid has been associated with Application {$application->uuid}");
      $application_environments = $environments_resource->getAll($application->uuid);
      foreach ($application_environments as $application_environment) {
        $response = $client->request('post', "/environments/{$application_environment->uuid}/email/actions/enable");
        $this->io->success("Platform email has been enabled for environment {$application_environment->name} for application {$application->name}");
      }
    }

    $this->io->success("The last step is enabling Platform Email for the environments of an application!");
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
  protected function createDnsText(Client $client, $subscription, $domain_uuid): void {
    $domain_registration_response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
    $records = [];
    foreach ($domain_registration_response as $record) {
      unset($record->health);
      $records[] = $record;
    }
    $this->localMachineHelper->getFilesystem()->remove('dns-records.txt');
    $this->localMachineHelper->getFilesystem()
      ->dumpFile('dns-records.txt', json_encode($records));
  }

  /**
   * Polls the Cloud Platform until a successful SSH request is made to the dev
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

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $client, $subscription, $domain_uuid, $spinner) {
      try {
        $response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
        if ($response->health->code === 200) {
          LoopHelper::finishSpinner($spinner);
          $loop->stop();
          $output->writeln("\n<info>Your domain is ready for use!</info>\n");
        }
        else {
          $this->logger->debug(json_decode($response));
        }
      } catch (AcquiaCliException $exception) {
        // Do nothing. Keep waiting and looping and logging.
        $this->logger->debug($exception->getMessage());
      }
    });
    LoopHelper::addTimeoutToLoop($loop, 15, $spinner);
    $loop->run();
  }

}
