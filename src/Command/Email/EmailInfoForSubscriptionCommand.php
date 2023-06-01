<?php

namespace Acquia\Cli\Command\Email;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Response\SubscriptionResponse;
use League\Csv\Writer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EmailInfoForSubscriptionCommand extends CommandBase {

  // phpcs:ignore
  protected static $defaultName = 'email:info';

  protected function configure(): void {
    $this->setDescription('Print information related to Platform Email set up in a subscription.')
      ->addArgument('subscriptionUuid', InputArgument::OPTIONAL, 'The subscription UUID whose Platform Email configuration is to be checked.')
      ->setHelp('This command lists information related to Platform Email for a subscription, including which domains have been validated, which have not, and which applications have Platform Email domains associated.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {

    $client = $this->cloudApiClientService->getClient();
    $subscription = $this->determineCloudSubscription();

    $response = $client->request('get', "/subscriptions/$subscription->uuid/domains");

    if (count($response)) {

      $this->localMachineHelper->getFilesystem()->remove("./subscription-$subscription->uuid-domains");
      $this->localMachineHelper->getFilesystem()->mkdir("./subscription-$subscription->uuid-domains");

      $this->writeDomainsToTables($output, $subscription, $response);

      $subscriptionApplications = $this->validateSubscriptionApplicationCount($client, $subscription);

      if (!isset($subscriptionApplications)) {
        return 1;
      }

      $this->renderApplicationAssociations($output, $client, $subscription, $subscriptionApplications);

      $this->output->writeln("<info>CSV files with these tables have been exported to <options=bold>/subscription-$subscription->uuid-domains</>. A detailed breakdown of each domain's DNS records has been exported there as well.</info>");
    }
    else {
      $this->io->info("No email domains registered in $subscription->name.");
    }

    return Command::SUCCESS;
  }

  /**
   * Renders tables showing email domain verification statuses,
   * as well as exports these statuses to respective CSV files.
   *
   * @param array $domainList
   */
  private function writeDomainsToTables(OutputInterface $output, SubscriptionResponse $subscription, array $domainList): void {

    // initialize tables to be displayed in console
    $allDomainsTable = $this->createTotalDomainTable($output, "Subscription $subscription->name - All Domains");
    $verifiedDomainsTable = $this->createDomainStatusTable($output, "Subscription $subscription->name - Verified Domains");
    $pendingDomainsTable = $this->createDomainStatusTable($output, "Subscription $subscription->name - Pending Domains");
    $failedDomainsTable = $this->createDomainStatusTable($output, "Subscription $subscription->name - Failed Domains");

    // initialize csv writers for each file
    $writerAllDomains = Writer::createFromPath("./subscription-$subscription->uuid-domains/all-domains-summary.csv", 'w+');
    $writerVerifiedDomains = Writer::createFromPath("./subscription-$subscription->uuid-domains/verified-domains-summary.csv", 'w+');
    $writerPendingDomains = Writer::createFromPath("./subscription-$subscription->uuid-domains/pending-domains-summary.csv", 'w+');
    $writerFailedDomains = Writer::createFromPath("./subscription-$subscription->uuid-domains/failed-domains-summary.csv", 'w+');
    $writerAllDomainsDnsHealth = Writer::createFromPath("./subscription-$subscription->uuid-domains/all-domains-dns-health.csv", 'w+');

    $allDomainsSummaryHeader = ['Domain Name', 'Domain UUID', 'Verification Status'];
    $writerAllDomains->insertOne($allDomainsSummaryHeader);

    $verifiedDomainsHeader = ['Domain Name', 'Summary'];
    $writerVerifiedDomains->insertOne($verifiedDomainsHeader);

    $pendingDomainsHeader = $verifiedDomainsHeader;
    $writerPendingDomains->insertOne($pendingDomainsHeader);

    $failedDomainsHeader = $verifiedDomainsHeader;
    $writerFailedDomains->insertOne($failedDomainsHeader);

    $allDomainsDnsHealthCsvHeader = ['Domain Name', 'Domain UUID', 'Domain Health', 'DNS Record Name', 'DNS Record Type', 'DNS Record Value', 'DNS Record Health Details'];
    $writerAllDomainsDnsHealth->insertOne($allDomainsDnsHealthCsvHeader);

    foreach ($domainList as $domain) {
      $domainNameAndSummary = [$domain->domain_name, $domain->health->summary];

      if ($domain->health->code === '200') {
        $verifiedDomainsTable->addRow($domainNameAndSummary);
        $writerVerifiedDomains->insertOne($domainNameAndSummary);
      }
      else if ($domain->health->code === '202') {
        $pendingDomainsTable->addRow($domainNameAndSummary);
        $writerPendingDomains->insertOne($domainNameAndSummary);
      }
      else {
        $failedDomainsTable->addRow($domainNameAndSummary);
        $writerFailedDomains->insertOne($domainNameAndSummary);
      }

      $allDomainsTable->addRow([
        $domain->domain_name,
        $domain->uuid,
        $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code,
      ]);

      $writerAllDomains->insertOne([
        $domain->domain_name,
        $domain->uuid,
        $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code,
      ]);

      foreach ($domain->dns_records as $index => $record) {
        if ($index === 0) {
          $writerAllDomainsDnsHealth->insertOne([
            $domain->domain_name,
            $domain->uuid,
            $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code,
            $record->name,
            $record->type,
            $record->value,
            $record->health->details,
          ]);
        }
        else {
          $writerAllDomainsDnsHealth->insertOne([
            '',
            '',
            '',
            $record->name,
            $record->type,
            $record->value,
            $record->health->details,
          ]);
        }
      }
    }

    $this->renderDomainInfoTables([$allDomainsTable, $verifiedDomainsTable, $pendingDomainsTable, $failedDomainsTable]);

  }

  /**
   * Nicely renders a given array of tables.
   *
   * @param array $tables
   */
  private function renderDomainInfoTables(array $tables): void {
    foreach ($tables as $table) {
      $table->render();
      $this->io->newLine();
    }
  }

  /**
   * Verifies the number of applications present in a subscription.
   *
   * @return array|null
   */
  private function validateSubscriptionApplicationCount(Client $client, SubscriptionResponse $subscription): ?array {
    $subscriptionApplications = $this->getSubscriptionApplications($client, $subscription);
    if (count($subscriptionApplications) > 100) {
      $this->io->warning('You have over 100 applications in this subscription. Retrieving the email domains for each could take a while!');
      $continue = $this->io->confirm('Do you wish to continue?');
      if (!$continue) {
        return NULL;
      }
    }

    return $subscriptionApplications;
  }

  /**
   * Renders a table of applications in a subscription and the email domains
   * associated or dissociated with each application.
   *
   * @param $subscription
   * @param $subscriptionApplications
   */
  private function renderApplicationAssociations(OutputInterface $output, Client $client, $subscription, $subscriptionApplications): void {
    $appsDomainsTable = $this->createApplicationDomainsTable($output);
    $writerAppsDomains = Writer::createFromPath("./subscription-$subscription->uuid-domains/apps-domain-associations.csv", 'w+');

    $appsDomainsHeader = ['Application', 'Domain Name', 'Associated?'];
    $writerAppsDomains->insertOne($appsDomainsHeader);

    foreach ($subscriptionApplications as $index => $app) {
      $appDomains = $client->request('get', "/applications/$app->uuid/email/domains");

      if ($index !== 0) {
        $appsDomainsTable->addRow([new TableSeparator(['colspan' => 2])]);
      }
      $appsDomainsTable->addRow([new TableCell("Application: $app->name", ['colspan' => 2])]);
      if (count($appDomains)) {
        foreach ($appDomains as $domain) {
          $appsDomainsTable->addRow([
            $domain->domain_name,
            var_export($domain->flags->associated, TRUE),
          ]);
          $writerAppsDomains->insertOne([$app->name, $domain->domain_name, var_export($domain->flags->associated, TRUE)]);
        }
      }
      else {
        $appsDomainsTable->addRow([new TableCell("No domains eligible for association.", [
          'colspan' => 2,
          'style' => new TableCellStyle([
            'fg' => 'yellow',
          ]),
        ]),
        ]);
        $writerAppsDomains->insertOne([$app->name, 'No domains eligible for association', '']);
      }
    }
    $appsDomainsTable->render();
  }

  /**
   * Creates a table of all domains registered in a subscription.
   */
  private function createTotalDomainTable(OutputInterface $output, string $title): Table {
    $headers = ['Domain Name', 'Domain UUID', 'Verification Status'];
    $widths = [.2, .2, .1];
    return $this->createTable($output, $title, $headers, $widths);
  }

  /**
   * Creates a table of domains of one verification status in a subscription.
   */
  private function createDomainStatusTable(OutputInterface $output, string $title): Table {
    $headers = ['Domain Name', 'Summary'];
    $widths = [.2, .2];
    return $this->createTable($output, $title, $headers, $widths);
  }

  /**
   * Creates a table of applications in a subscription and the associated
   * or dissociated domains in each application.
   */
  private function createApplicationDomainsTable(OutputInterface $output): Table {
    $headers = ['Domain Name', 'Associated?'];
    $widths = [.2, .1];
    return $this->createTable($output, 'Domain Association Status', $headers, $widths);
  }

  /**
   * Returns a human-readable string of whether a status code represents
   * a failed, pending, or successful domain verification.
   */
  private function showHumanReadableStatus(string $code): string {
    return match ($code) {
      '200' => "Succeeded",
      '202' => "Pending",
      default => "Failed",
    };
  }

}
