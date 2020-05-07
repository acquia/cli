<?php

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AliasListCommand.
 */
class AliasListCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('remote:aliases:list')->setDescription('List all aliases for Acquia Cloud environments');
    // @todo Add option to allow specifying application.
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->getAcquiaCloudClient();
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    $environments_resource = new Environments($acquia_cloud_client);

    $table = new Table($this->output);
    $table->setHeaders(['Environment Alias', 'Application', 'Environment UUID']);

    $count = count($customer_applications);
    $progressBar = new ProgressBar($output, $count);
    $progressBar->setFormat('message');
    $progressBar->setMessage("Fetching aliases for <comment>$count applications</comment> from Acquia Cloud...");
    $progressBar->start();
    foreach ($customer_applications as $customer_application) {
      $progressBar->setMessage("Fetching aliases for <comment>{$customer_application->name}</comment>");
      $site_id = $customer_application->hosting->id;
      $parts = explode(':', $site_id);
      $site_prefix = $parts[1];
      $environments = $environments_resource->getAll($customer_application->uuid);
      foreach ($environments as $environment) {
        $alias = $site_prefix . '.' . $environment->name;
        $table->addRow([$customer_application->name, $alias, $environment->uuid]);
      }
      $progressBar->advance();
    }

    $progressBar->finish();
    $table->render();

    return 0;
  }

}
