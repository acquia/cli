<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaLogstream\LogstreamManager;
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
    $log_types = array_map(function ($log) {
      return $log->type;
    }, $logs);
    $logs_resource = new Logs($acquia_cloud_client);
    $stream = $logs_resource->stream($environment_id);
    $logstream = $this->getApplication()->logStreamManager;
    $logstream->setParams($stream->logstream->params);
    $logstream->setColourise(TRUE);
    $logstream->setLogTypeFilter($log_types);
    $output->writeln("Streaming has started and new logs will appear below. Use Ctrl+C to exit.");
    $logstream->stream();
    return 0;
  }

}
