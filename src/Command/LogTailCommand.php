<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LogTailCommand.
 */
class LogTailCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('log:tail')->setDescription('Tail the logs from your environments');
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
    $environment_id = $this->determineCloudEnvironment($application_uuid);
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $logs = $this->promptChooseLogs($acquia_cloud_client, $environment_id);
    // Now need to connect via websocket to logstream server and filter by chosen logs.
    return 0;
  }

}
