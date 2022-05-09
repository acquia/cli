<?php

namespace Acquia\Cli\Command\Email;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Response\SubscriptionResponse;
use League\Csv\Writer;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Class EmailInfoForSubscriptionCommand.
 */
class EmailInfoForSubscriptionCommand extends CommandBase {

  protected static $defaultName = 'email:info';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Print information related to Platform Email set up in a subscription.')
      ->addArgument('subscriptionUuid', InputArgument::OPTIONAL, 'The subscription UUID whose Platform Email configuration is to be checked.')
      ->setHelp('This command lists information related to Platform Email for a subscription, including which domains have been validated, which have not, and which applications have Platform Email domains associated.');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    $client = $this->cloudApiClientService->getClient();
    $subscription = $this->determineCloudSubscription();

    $response = $client->request('get', "/subscriptions/{$subscription->uuid}/domains");

    if (count($response)) {

      $this->localMachineHelper->getFilesystem()->remove("./subscription-{$subscription->uuid}-domains");
      $this->localMachineHelper->getFilesystem()->mkdir("./subscription-{$subscription->uuid}-domains");

      $this->writeDomainsToTables($output, $subscription, $response);

      $subscription_applications = $this->validateSubscriptionApplicationCount($client, $subscription);

      if (!isset($subscription_applications)) {
        return 1;
      }

      $this->renderApplicationAssociations($output, $client, $subscription, $subscription_applications);

      $this->output->writeln("<info>CSV files with these tables have been exported to <options=bold>/subscription-{$subscription->uuid}-domains</>. A detailed breakdown of each domain's DNS records has been exported there as well.</info>");
    }
    else {
      $this->io->info("No email domains registered in {$subscription->name}.");
    }

    return 0;
  }

  /**
   * Renders tables showing email domain verification statuses,
   * as well as exports these statuses to respective CSV files.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \AcquiaCloudApi\Response\SubscriptionResponse $subscription
   * @param array $domain_list
   *
   * @return void
   * @throws \League\Csv\CannotInsertRecord
   */
  protected function writeDomainsToTables(OutputInterface $output, SubscriptionResponse $subscription, array $domain_list) {

    // initialize tables to be displayed in console
    $all_domains_table = $this->createTotalDomainTable($output, "Subscription {$subscription->name} - All Domains");
    $verified_domains_table = $this->createDomainStatusTable($output, "Subscription {$subscription->name} - Verified Domains");
    $pending_domains_table = $this->createDomainStatusTable($output, "Subscription {$subscription->name} - Pending Domains");
    $failed_domains_table = $this->createDomainStatusTable($output, "Subscription {$subscription->name} - Failed Domains");

    // initialize csv writers for each file
    $writer_all_domains = Writer::createFromPath("./subscription-{$subscription->uuid}-domains/all-domains-summary.csv", 'w+');
    $writer_verified_domains = Writer::createFromPath("./subscription-{$subscription->uuid}-domains/verified-domains-summary.csv", 'w+');
    $writer_pending_domains = Writer::createFromPath("./subscription-{$subscription->uuid}-domains/pending-domains-summary.csv", 'w+');
    $writer_failed_domains = Writer::createFromPath("./subscription-{$subscription->uuid}-domains/failed-domains-summary.csv", 'w+');
    $writer_all_domains_dns_health = Writer::createFromPath("./subscription-{$subscription->uuid}-domains/all-domains-dns-health.csv", 'w+');

    $all_domains_summary_header = ['Domain Name', 'Domain UUID', 'Verification Status'];
    $writer_all_domains->insertOne($all_domains_summary_header);

    $verified_domains_header = ['Domain Name', 'Summary'];
    $writer_verified_domains->insertOne($verified_domains_header);

    $pending_domains_header = $verified_domains_header;
    $writer_pending_domains->insertOne($pending_domains_header);

    $failed_domains_header = $verified_domains_header;
    $writer_failed_domains->insertOne($failed_domains_header);

    $all_domains_dns_health_csv_header = ['Domain Name', 'Domain UUID', 'Domain Health', 'DNS Record Name', 'DNS Record Type', 'DNS Record Value', 'DNS Record Health Details'];
    $writer_all_domains_dns_health->insertOne($all_domains_dns_health_csv_header);

    foreach ($domain_list as $domain) {
      $domain_name_and_summary = [$domain->domain_name, $domain->health->summary];

      if ($domain->health->code === '200') {
        $verified_domains_table->addRow($domain_name_and_summary);
        $writer_verified_domains->insertOne($domain_name_and_summary);
      }
      else if ($domain->health->code === '202') {
        $pending_domains_table->addRow($domain_name_and_summary);
        $writer_pending_domains->insertOne($domain_name_and_summary);
      }
      else {
        $failed_domains_table->addRow($domain_name_and_summary);
        $writer_failed_domains->insertOne($domain_name_and_summary);
      }

      $all_domains_table->addRow([
        $domain->domain_name,
        $domain->uuid,
        $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code
      ]);

      $writer_all_domains->insertOne([
        $domain->domain_name,
        $domain->uuid,
        $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code
      ]);

      foreach($domain->dns_records as $index => $record) {
        if ($index === 0) {
          $writer_all_domains_dns_health->insertOne([
            $domain->domain_name,
            $domain->uuid,
            $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code,
            $record->name,
            $record->type,
            $record->value,
            $record->health->details
          ]);
        }
        else {
          $writer_all_domains_dns_health->insertOne([
            '',
            '',
            '',
            $record->name,
            $record->type,
            $record->value,
            $record->health->details
          ]);
        }
      }
    }

    $this->renderDomainInfoTables([$all_domains_table, $verified_domains_table, $pending_domains_table, $failed_domains_table]);

  }

  /**
   * Nicely renders a given array of tables.
   *
   * @param array $tables
   *
   * @return void
   */
  protected function renderDomainInfoTables(array $tables) {
    foreach ($tables as $table) {
      $table->render();
      $this->io->newLine();
    }
  }

  /**
   * Verifies the number of applications present in a subscription.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   * @param \AcquiaCloudApi\Response\SubscriptionResponse $subscription
   *
   * @return array|null
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateSubscriptionApplicationCount(Client $client, SubscriptionResponse $subscription) {
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
    if (count($subscription_applications) > 100) {
      $this->io->warning('You have over 100 applications in this subscription. Retrieving the email domains for each could take a while!');
      $continue = $this->io->confirm('Do you wish to continue?');
      if (!$continue) {
        return NULL;
      }
    }

    return $subscription_applications;
  }

  /**
   * Renders a table of applications in a subscription and the email domains
   * associated or dissociated with each application.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \AcquiaCloudApi\Connector\Client $client
   * @param $subscription_applications
   *
   * @return void
   * @throws \League\Csv\CannotInsertRecord
   */
  protected function renderApplicationAssociations(OutputInterface $output, Client $client, $subscription, $subscription_applications) {
    $apps_domains_table = $this->createApplicationDomainsTable($output, 'Domain Association Status');
    $writer_apps_domains = Writer::createFromPath("./subscription-{$subscription->uuid}-domains/apps-domain-associations.csv", 'w+');

    $apps_domains_header = ['Application', 'Domain Name', 'Associated?'];
    $writer_apps_domains->insertOne($apps_domains_header);

    foreach($subscription_applications as $index => $app) {
      $app_domains = $client->request('get', "/applications/{$app->uuid}/email/domains");

      if ($index !== 0) {
        $apps_domains_table->addRow([new TableSeparator(['colspan' => 2])]);
      }
      $apps_domains_table->addRow([new TableCell("Application: {$app->name}", ['colspan' => 2])]);
      if(count($app_domains)) {
        foreach($app_domains as $domain) {
          $apps_domains_table->addRow([
            $domain->domain_name,
            var_export($domain->flags->associated, TRUE)
          ]);
          $writer_apps_domains->insertOne([$app->name, $domain->domain_name, var_export($domain->flags->associated, TRUE)]);
        }
      }
      else {
        $apps_domains_table->addRow([new TableCell("No domains eligible for association.", [
          'colspan' => 2,
          'style' => new TableCellStyle([
            'fg' => 'yellow'
          ]),
        ])
        ]);
        $writer_apps_domains->insertOne([$app->name, 'No domains eligible for association', '']);
      }
    }
    $apps_domains_table->render();
  }

  /**
   * Creates a table of all domains registered in a subscription.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $title
   *
   * @return \Symfony\Component\Console\Helper\Table
   */
  protected function createTotalDomainTable(OutputInterface $output, string $title): Table {
    $headers = ['Domain Name', 'Domain UUID', 'Verification Status'];
    $widths = [.2, .2, .1];
    return $this->createTable($output, $title, $headers, $widths);
  }

  /**
   * Creates a table of domains of one verification status in a subscription.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $title
   *
   * @return \Symfony\Component\Console\Helper\Table
   */
  protected function createDomainStatusTable(OutputInterface $output, string $title): Table {
    $headers = ['Domain Name', 'Summary'];
    $widths = [.2, .2];
    return $this->createTable($output, $title, $headers, $widths);
  }

  /**
   * Creates a table of applications in a subscription and the associated
   * or dissociated domains in each application.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $title
   *
   * @return \Symfony\Component\Console\Helper\Table
   */
  protected function createApplicationDomainsTable(OutputInterface $output, string $title): Table {
    $headers = ['Domain Name', 'Associated?'];
    $widths = [.2, .1];
    return $this->createTable($output, $title, $headers, $widths);
  }

  /**
   * Returns a human-readable string of whether a status code represents
   * a failed, pending, or successful domain verification.
   *
   * @param string $code
   *
   * @return string
   */
  protected function showHumanReadableStatus(string $code): string {
    switch ($code) {
      case '200':
          return "Succeeded";
      case '202':
          return "Pending";
      default:
          return "Failed";
    }
  }

}
