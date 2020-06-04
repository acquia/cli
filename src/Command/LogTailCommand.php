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

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('log:tail')
      ->setDescription('Tail the logs from your environments')
      ->addOption('cloud-env-uuid', 'uuid', InputOption::VALUE_REQUIRED, 'The UUID of the associated Acquia Cloud environment');
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
    $acquia_cloud_client = $this->getApplication()->getContainer()->get('cloud_api')->getClient();
    $logs = $this->promptChooseLogs($acquia_cloud_client, $environment_id);
    $log_types = array_map(function ($log) {
      return $log->type;
    }, $logs);
    $logs_resource = new Logs($acquia_cloud_client);
    $stream = $logs_resource->stream($environment_id);
    $logstream = $this->getApplication()->getContainer()->get('logstream_manager');
    $logstream->setParams($stream->logstream->params);
    $logstream->setColourise(TRUE);
    $logstream->setLogTypeFilter($log_types);
    $output->writeln('<info>Streaming has started and new logs will appear below. Use Ctrl+C to exit.</info>');
    $logstream->stream();
    return 0;
  }

}
