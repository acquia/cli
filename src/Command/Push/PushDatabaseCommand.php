<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushDatabaseCommand extends PullCommandBase {

  protected static $defaultName = 'push:database';

  protected function configure(): void {
    $this->setDescription('Push a database from your IDE to a Cloud Platform environment')
      ->setAliases(['push:db'])
      ->acceptEnvironmentId()
      ->acceptSite()
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $destinationEnvironment = $this->determineEnvironment($input, $output);
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $databases = $this->determineCloudDatabases($acquiaCloudClient, $destinationEnvironment, $input->getArgument('site'));
    // We only support pushing a single database.
    $database = $databases[0];
    $answer = $this->io->confirm("Overwrite the <bg=cyan;options=bold>{$database->name}</> database on <bg=cyan;options=bold>{$destinationEnvironment->name}</> with a copy of the database from the current machine?");
    if (!$answer) {
      return 0;
    }

    $this->checklist = new Checklist($output);
    $outputCallback = $this->getOutputCallback($output, $this->checklist);

    $this->checklist->addItem('Creating local database dump');
    $localDumpFilepath = $this->createMySqlDumpOnLocal($this->getDefaultLocalDbHost(), $this->getDefaultLocalDbUser(), $this->getDefaultLocalDbName(), $this->getDefaultLocalDbPassword(), $outputCallback);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Uploading database dump to remote machine');
    $remoteDumpFilepath = $this->uploadDatabaseDump($destinationEnvironment, $localDumpFilepath, $outputCallback);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Importing database dump into MySQL on remote machine');
    $this->importDatabaseDumpOnRemote($destinationEnvironment, $remoteDumpFilepath, $database);
    $this->checklist->completePreviousItem();

    return 0;
  }

  private function uploadDatabaseDump(
    EnvironmentResponse $environment,
    string $localFilepath,
    callable $outputCallback
  ): string {
    $sitegroup = self::getSiteGroupFromSshUrl($environment->sshUrl);
    $remoteFilepath = "/mnt/tmp/{$sitegroup}.{$environment->name}/" . basename($localFilepath);
    $this->logger->debug("Uploading database dump to $remoteFilepath on remote machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
    $command = [
      'rsync',
      '-tDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $localFilepath,
      $environment->sshUrl . ':' . $remoteFilepath,
    ];
    $process = $this->localMachineHelper->execute($command, $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Could not upload local database dump: {message}',
        ['message' => $process->getOutput()]);
    }

    return $remoteFilepath;
  }

  private function importDatabaseDumpOnRemote(EnvironmentResponse $environment, string $remoteDumpFilepath, DatabaseResponse $database): void {
    $this->logger->debug("Importing $remoteDumpFilepath to MySQL on remote machine");
    $command = "pv $remoteDumpFilepath --bytes --rate | gunzip | MYSQL_PWD={$database->password} mysql --host={$this->getHostFromDatabaseResponse($environment, $database)} --user={$database->user_name} {$this->getNameFromDatabaseResponse($database)}";
    $process = $this->sshHelper->executeCommand($environment, [$command], ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to import database on remote machine. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  private function getNameFromDatabaseResponse(DatabaseResponse $database): string {
    $dbUrlParts = explode('/', $database->url);
    return end($dbUrlParts);
  }

}
