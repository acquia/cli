<?php

namespace Acquia\Cli\Command\Email;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Applications;
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
      $all_domains_table = $this->createTotalDomainTable($output, "Subscription {$subscription->name} - All Domains");
      $verified_domains_table = $this->createDomainStatusTable($output, "Subscription {$subscription->name} - Verified Domains");
      $pending_domains_table = $this->createDomainStatusTable($output, "Subscription {$subscription->name} - Pending Domains");
      $failed_domains_table = $this->createDomainStatusTable($output, "Subscription {$subscription->name} - Failed Domains");

      foreach ($response as $domain) {
        if ($domain->health->code === '200') {
          $verified_domains_table->addRow([
            $domain->domain_name,
            $domain->uuid,
            'none'
          ]);
        }
        else if ($domain->health->code === '202') {
          $pending_domains_table->addRow([
            $domain->domain_name,
            $domain->uuid,
            'none'
          ]);
        }
        else {
          $failed_domains_table->addRow([
            $domain->domain_name,
            $domain->uuid,
            $domain->health->summary
          ]);
        }

        $all_domains_table->addRow([
          $domain->domain_name,
          $domain->uuid,
          $this->showHumanReadableStatus($domain->health->code) . ' - ' . $domain->health->code
        ]);
      }

      $all_domains_table->render();
      $this->io->newLine();

      $verified_domains_table->render();
      $this->io->newLine();

      $pending_domains_table->render();
      $this->io->newLine();

      $failed_domains_table->render();
      $this->io->newLine();

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
          return 1;
        }
      }
      $apps_domains_table = $this->createApplicationDomainsTable($output, 'Domain Association Status');
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
        }
      }
      $apps_domains_table->render();
    }
    else {
      $this->io->info("No email domains registered in {$subscription->name}.");
    }

    return 0;
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
    $terminal_width = (new Terminal())->getWidth();
    $terminal_width *= .90;
    $table = new Table($output);
    $table->setHeaders([
      'Domain Name',
      'Domain UUID',
      'Verification Status',
    ]);
    $table->setHeaderTitle($title);
    $table->setColumnWidths([
      $terminal_width * .2,
      $terminal_width * .2,
      $terminal_width * .1,
    ]);

    return $table;
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
    $terminal_width = (new Terminal())->getWidth();
    $terminal_width *= .90;
    $table = new Table($output);
    $table->setHeaders([
      'Domain Name',
      'Domain UUID',
      'Summary',
    ]);
    $table->setHeaderTitle($title);
    $table->setColumnWidths([
      $terminal_width * .2,
      $terminal_width * .2,
      $terminal_width * .2,
    ]);

    return $table;
  }

  /**
   * Creates a table of domains of one verification status in a subscription.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $title
   *
   * @return \Symfony\Component\Console\Helper\Table
   */
  protected function createApplicationDomainsTable(OutputInterface $output, string $title): Table {
    $terminal_width = (new Terminal())->getWidth();
    $terminal_width *= .90;
    $table = new Table($output);
    $table->setHeaders([
      'Domain Name',
      'Associated?',
    ]);
    $table->setHeaderTitle($title);
    $table->setColumnWidths([
      $terminal_width * .2,
      $terminal_width * .1,
    ]);

    return $table;
  }

  /**
   * Returns a human-readable string of whether a status code represents
   * a failed, pending, or successful domain verification.
   *
   * @param string $code
   *
   * @return string
   */
  protected function showHumanReadableStatus(string $code) {
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