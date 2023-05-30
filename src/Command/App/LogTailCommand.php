<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Logs;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LogTailCommand extends CommandBase {

  protected static $defaultName = 'app:log:tail';

  protected function configure(): void {
    $this->setDescription('Tail the logs from your environments')
      ->acceptEnvironmentId()
      ->setAliases(['tail', 'log:tail']);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $environmentId = $this->determineCloudEnvironment();
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $logs = $this->promptChooseLogs();
    $logTypes = array_map(static function ($log) {
      return $log['type'];
    }, $logs);
    $logsResource = new Logs($acquiaCloudClient);
    $stream = $logsResource->stream($environmentId);
    $this->logstreamManager->setParams($stream->logstream->params);
    $this->logstreamManager->setColourise(TRUE);
    $this->logstreamManager->setLogTypeFilter($logTypes);
    $output->writeln('<info>Streaming has started and new logs will appear below. Use Ctrl+C to exit.</info>');
    $this->logstreamManager->stream();
    return Command::SUCCESS;
  }

}
