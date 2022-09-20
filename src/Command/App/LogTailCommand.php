<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Logs;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LogTailCommand.
 */
class LogTailCommand extends CommandBase {

  protected static $defaultName = 'app:log:tail';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Tail the logs from your environments')
      ->acceptEnvironmentId()
      ->setAliases(['tail', 'log:tail']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $environment_id = $this->determineCloudEnvironment();
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $logs = $this->promptChooseLogs();
    $log_types = array_map(static function ($log) {
      return $log['type'];
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
