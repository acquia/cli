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
use Closure;
use LTDBeget\dns\configurator\Zone;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

class ConfigurePlatformEmailCommand extends CommandBase {

  protected static $defaultName = 'email:configure';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Configure Platform email for one or more applications')
      ->addArgument('subscriptionUuid', InputArgument::OPTIONAL, 'The subscription UUID to register the domain with.')
      ->setHelp('This command configures Platform Email for a domain in a subscription. It registers the domain with the subscription, associates the domain with an application or set of applications, and enables Platform Email for selected environments of these applications.')
      ->setAliases(['ec']);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io->writeln('Welcome to Platform Email setup! This script will walk you through the whole process setting up Platform Email, all through the command line and using the Cloud API!');
    $this->io->writeln('Before getting started, make sure you have the following: ');

    $checklist = new Checklist($output);
    $checklist->addItem('the domain name you are registering');
    $checklist->completePreviousItem();
    $checklist->addItem('the subscription where the domain will be registered');
    $checklist->completePreviousItem();
    $checklist->addItem('the application or applications where the domain will be associated');
    $checklist->completePreviousItem();
    $checklist->addItem('the environment or environments for the above applications where Platform Email will be enabled');
    $checklist->completePreviousItem();
    $base_domain = $this->determineDomain();
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
      "We will create a file with the DNS records for your newly registered domain",
      "Provide these records to your DNS provider",
      "After you've done this, continue to domain verification."
    ]);
    $file_format = $this->io->choice('Would you like your DNS records in BIND Zone File, JSON, or YAML format?', ['BIND Zone File', 'YAML', 'JSON'], 'BIND Zone File');
    $this->createDnsText($client, $subscription, $base_domain, $domain_uuid, $file_format);
    $continue = $this->io->confirm('Have you finished providing the DNS records to your DNS provider?');
    if (!$continue) {
      $this->io->info("Make sure to give these records to your DNS provider, then rerun this script with the domain that you just registered.");
      return 1;
    }

    // Allow for as many reverification tries as needed.
    while (!$this->checkIfDomainVerified($subscription, $domain_uuid)) {
      $retry_verification = $this->io->confirm('Would you like to re-check domain verification?');
      if (!$retry_verification) {
        $this->io->writeln('Check your DNS records with your DNS provider and try again by rerunning this script with the domain that you just registered.');
        return 1;
      }
    }

    $this->io->success("The next step is associating your verified domain with an application (or applications) in the subscription where your domain has been registered.");

    if (!$this->addDomainToSubscriptionApplications($client, $subscription, $base_domain, $domain_uuid)) {
      $this->io->error('Something went wrong with associating your application(s) or enabling your environment(s). Try again.');
      return 1;
    }

    $this->io->success("You're all set to start using Platform Email!");

    return 0;
  }

  /**
   * Generates Zone File for DNS records of the registered domain.
   *
   * @param array $records
   */
  private function generateZoneFile(string $base_domain, array $records): void {

    $zone = new Zone($base_domain . '.');

    foreach ($records as $record) {
      unset($record->health);
      $record_to_add = $zone->getNode($record->name . '.');

      switch ($record->type) {
        case 'MX':
          $mx_priority_value_arr = explode(' ', $record->value);
          $record_to_add->getRecordAppender()->appendMxRecord($mx_priority_value_arr[0], $mx_priority_value_arr[1] . '.', 3600);
          break;
        case 'TXT':
          $record_to_add->getRecordAppender()->appendTxtRecord($record->value, 3600);
          break;
        case 'CNAME':
          $record_to_add->getRecordAppender()->appendCNameRecord($record->value . '.', 3600);
          break;
      }
    }

    $this->localMachineHelper->getFilesystem()
      ->dumpFile('dns-records.zone', (string) $zone);

  }

  /**
   * Determines the applications for domain association and environment
   * enablement of Platform Email.
   *
   * @return array
   */
  private function determineApplications(Client $client, SubscriptionResponse $subscription): array {
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

    if (count($subscription_applications) === 1) {
      $applications = $subscription_applications;
      $this->io->info("You have one application, {$applications[0]->name}, in this subscription.");
    }
    else {
      $applications = $this->promptChooseFromObjectsOrArrays($subscription_applications, 'uuid', 'name', "What are the applications you'd like to associate this domain with? You may enter multiple separated by a comma.", TRUE);
    }
    return $applications;
  }

  /**
   * Checks any error from Cloud API when associating a domain with an application.
   * Shows a warning and allows user to continue if the domain has been associated already.
   * For any other error from the API, the setup will exit.
   */
  private function domainAlreadyAssociated(object $application, ApiErrorException $exception): ?bool {
    if (!str_contains($exception, 'is already associated with this application')) {
      $this->io->error($exception->getMessage());
      return FALSE;
    }

    $this->io->warning($application->name . ' - ' . $exception->getMessage());
    return TRUE;
  }

  /**
   * Checks any error from Cloud API when enabling Platform Email for an environment.
   * Shows a warning and allows user to continue if Platform Email has already been enabled for the environment.
   * For any other error from the API, the setup will exit.
   */
  private function environmentAlreadyEnabled(object $environment, ApiErrorException $exception): ?bool {
    if (!str_contains($exception, 'is already enabled on this environment')) {
      $this->io->error($exception->getMessage());
      return FALSE;
    }

    $this->io->warning($environment->label . ' - ' . $exception->getMessage());
    return TRUE;
  }

  /**
   * Associates a domain with an application or applications,
   * then enables Platform Email for an environment or environments
   * of the above applications.
   */
  private function addDomainToSubscriptionApplications(Client $client, SubscriptionResponse $subscription, string $base_domain, string $domain_uuid): bool {
    $applications = $this->determineApplications($client, $subscription);

    $environments_resource = new Environments($client);
    foreach ($applications as $application) {
      try {
        $response = $client->request('post', "/applications/{$application->uuid}/email/domains/{$domain_uuid}/actions/associate");
        $this->io->success("Domain $base_domain has been associated with Application {$application->name}");
      }
      catch (ApiErrorException $e) {
        if (!$this->domainAlreadyAssociated($application, $e)) {
          return FALSE;
        }
      }

      $application_environments = $environments_resource->getAll($application->uuid);
      $envs = $this->promptChooseFromObjectsOrArrays(
        $application_environments,
        'uuid',
        'label',
        "What are the environments of {$application->name} that you'd like to enable email for? You may enter multiple separated by a comma.",
        TRUE
      );
      foreach ($envs as $env) {
        try {
          $response = $client->request('post', "/environments/{$env->uuid}/email/actions/enable");
          $this->io->success("Platform Email has been enabled for environment {$env->label} for application {$application->name}");
        }
        catch (ApiErrorException $e) {
          if (!$this->environmentAlreadyEnabled($env, $e)) {
            return FALSE;
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Validates the URL entered as the base domain name.
   */
  public static function validateUrl(string $url): string {
    $constraints_list = [new NotBlank()];
    $url_parts = parse_url($url);
    if (array_key_exists('host', $url_parts)) {
      $constraints_list[] = new Url();
    }
    else {
      $constraints_list[] = new Hostname();
    }
    $violations = Validation::createValidator()->validate($url, $constraints_list);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    return $url;
  }

  /**
   * Retrieves a domain registration UUID given the domain name.
   */
  private function fetchDomainUuid(Client $client, SubscriptionResponse $subscription, string $base_domain): mixed {
    $domains_response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains");
    foreach ($domains_response as $domain) {
      if ($domain->domain_name === $base_domain) {
        return $domain->uuid;
      }
    }
    throw new AcquiaCliException("Could not find domain $base_domain");
  }

  /**
   * Creates a file, either in Bind Zone File, JSON or YAML format,
   * of the DNS records needed to complete Platform Email setup.
   */
  private function createDnsText(Client $client, SubscriptionResponse $subscription, string $base_domain, string $domain_uuid, string $file_format): void {
    $domain_registration_response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
    if (!isset($domain_registration_response->dns_records)) {
      throw new AcquiaCliException('Could not retrieve DNS records for this domain. Try again by rerunning this script with the domain that you just registered.');
    }
    $records = [];
    $this->localMachineHelper->getFilesystem()->remove('dns-records.json');
    $this->localMachineHelper->getFilesystem()->remove('dns-records.yaml');
    $this->localMachineHelper->getFilesystem()->remove('dns-records.zone');
    if ($file_format === 'JSON') {
      foreach ($domain_registration_response->dns_records as $record) {
        unset($record->health);
        $records[] = $record;
      }
      $this->logger->debug(json_encode($records, JSON_THROW_ON_ERROR));
      $this->localMachineHelper->getFilesystem()
            ->dumpFile('dns-records.json', json_encode($records, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    else if ($file_format === 'YAML') {
      foreach ($domain_registration_response->dns_records as $record) {
        unset($record->health);
        $records[] = ['type' => $record->type, 'name' => $record->name, 'value' => $record->value];
      }
      $this->logger->debug(json_encode($records, JSON_THROW_ON_ERROR));
      $this->localMachineHelper->getFilesystem()
            ->dumpFile('dns-records.yaml', Yaml::dump($records));
    }
    else {
      $this->generateZoneFile($base_domain, $domain_registration_response->dns_records);
    }

  }

  /**
   * Checks the verification status of the registered domain.
   */
  private function checkIfDomainVerified(
    SubscriptionResponse $subscription,
    string $domain_uuid
  ): bool {
    $client = $this->cloudApiClientService->getClient();
    try {
      $response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}");
      if (isset($response->health) && $response->health->code === "200") {
        $this->io->success("Your domain is ready for use!");
        return TRUE;
      }

      if (isset($response->health) && str_starts_with($response->health->code, "4")) {
        $this->io->error($response->health->details);
        $reverify = $this->io->confirm('Would you like to refresh?');
        if ($reverify) {
          $refresh_response = $client->request('post', "/subscriptions/{$subscription->uuid}/domains/{$domain_uuid}/actions/verify");
          $this->io->info('Refreshing...');
        }
      }

      $this->io->info("Verification pending...");
      $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));
      return FALSE;
    }
    catch (AcquiaCliException $exception) {
      $this->logger->debug($exception->getMessage());
      return FALSE;
    }
  }

  /**
   * Finds, validates, and trims the URL to be used as the base domain
   * for setting up Platform Email.
   */
  private function determineDomain(): string {
    $domain = $this->io->ask("What's the domain name you'd like to register?", '', Closure::fromCallable([
      $this,
      'validateUrl'
    ]));

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
