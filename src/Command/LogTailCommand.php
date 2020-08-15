<?php

namespace Acquia\Cli\Command;

use AcquiaCloudApi\Endpoints\Logs;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LogTailCommand.
 */
class LogTailCommand extends CommandBase {

  protected static $defaultName = 'log:tail';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Tail the logs from your environments')
      ->setAliases(['tail'])
      ->addOption('cloud-env-uuid', 'uuid', InputOption::VALUE_REQUIRED, 'The UUID of the associated Cloud Platform environment');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('cloud-env-uuid')) {
      $environment_id = $input->getOption('cloud-env-uuid');
    }
    else {
      $application_uuid = $this->determineCloudApplication();
      $environment_id = $this->determineCloudEnvironment($application_uuid);
    }
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $logs = $this->promptChooseLogs($acquia_cloud_client, $environment_id);
    $log_types = array_map(function ($log) {
      return $log->type;
    }, $logs);
    $logs_resource = new Logs($acquia_cloud_client);
    $stream = $logs_resource->stream($environment_id);
    $this->logstreamManager->setParams($stream->logstream->params);
    $this->logstreamManager->setColourise(TRUE);
    $this->logstreamManager->setLogTypeFilter($log_types);
    $output->writeln('<info>Streaming has started and new logs will appear below. Use Ctrl+C to exit.</info>');
    $this->logstreamManager->stream();
    return 0;
  }

}
