<?php

namespace Acquia\Cli\Command\Logs;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Logs;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LogsTailCommand.
 */
class LogsListCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('logs:list')->setDescription("List available logs.");
    // @todo Add option to accept environment uuid.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $application_uuid = $this->determineCloudApplication();
    $environment_uuid = $this->determineCloudEnvironment($application_uuid);
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $logs_resource = new Logs($acquia_cloud_client);
    $logs = $logs_resource->getAll($environment_uuid);
    $table = new Table($output);
    $table->setStyle('borderless');
    $table->setHeaders(['Type', 'Label', 'Available']);
    foreach ($logs as $log) {
      $table->addRows(
        [
          [
            "<comment>{$log->label}</comment>",
            $log->type,
            $log->flags->available ? '✓' : ' ',
          ],
        ]
      );
    }
    $table->render();

    return 0;
  }

}
