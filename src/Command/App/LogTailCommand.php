<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaLogstream\LogstreamManager;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'app:log:tail', description: 'Tail the logs from your environments', aliases: ['tail', 'log:tail'])]
final class LogTailCommand extends CommandBase {

  public function __construct(
    public LocalMachineHelper $localMachineHelper,
    protected CloudDataStore $datastoreCloud,
    protected AcquiaCliDatastore $datastoreAcli,
    protected ApiCredentialsInterface $cloudCredentials,
    protected TelemetryHelper $telemetryHelper,
    protected string $projectDir,
    protected ClientService $cloudApiClientService,
    public SshHelper $sshHelper,
    protected string $sshDir,
    LoggerInterface $logger,
    protected Client $httpClient,
    protected LogstreamManager $logstreamManager,
  ) {
    parent::__construct($this->localMachineHelper, $this->datastoreCloud, $this->datastoreAcli, $this->cloudCredentials, $this->telemetryHelper, $this->projectDir, $this->cloudApiClientService, $this->sshHelper, $this->sshDir, $logger, $this->httpClient);
  }

  protected function configure(): void {
    $this
      ->acceptEnvironmentId();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $environmentId = $this->determineCloudEnvironment();
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $logs = $this->promptChooseLogs();
    $logTypes = array_map(static function (mixed $log) {
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
