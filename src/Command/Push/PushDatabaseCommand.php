<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PushDatabaseCommand.
 */
class PushDatabaseCommand extends PullCommandBase {

  protected static $defaultName = 'push:database';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Push a database from your IDE to a Cloud Platform environment')
      ->setAliases(['push:db'])
      ->acceptEnvironmentId()
      ->acceptSite()
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $destination_environment = $this->determineEnvironment($input, $output, FALSE);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $databases = $this->determineCloudDatabases($acquia_cloud_client, $destination_environment, $input->getArgument('site'), FALSE);
    // We only support pushing a single database.
    $database = $databases[0];
    $answer = $this->io->confirm("Overwrite the <bg=cyan;options=bold>{$database->name}</> database on <bg=cyan;options=bold>{$destination_environment->name}</> with a copy of the database from the current machine?");
    if (!$answer) {
      return 0;
    }

    $this->checklist = new Checklist($output);
    $output_callback = $this->getOutputCallback($output, $this->checklist);

    $this->checklist->addItem('Creating local database dump');
    $local_dump_filepath = $this->createMySqlDumpOnLocal($this->getLocalDbHost(), $this->getLocalDbUser(), $this->getLocalDbName(), $this->getLocalDbPassword(), $output_callback);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Uploading database dump to remote machine');
    $remote_dump_filepath = $this->uploadDatabaseDump($destination_environment, $database, $local_dump_filepath, $output_callback);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem('Importing database dump into MySQL on remote machine');
    $this->importDatabaseDumpOnRemote($destination_environment, $remote_dump_filepath, $database);
    $this->checklist->completePreviousItem();

    return 0;
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param \AcquiaCloudApi\Response\DatabaseResponse $database
   * @param string $local_filepath
   * @param callable $output_callback
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function uploadDatabaseDump(
    $environment,
    $database,
    string $local_filepath,
    $output_callback
  ): string {
    $sitegroup = self::getSiteGroupFromSshUrl($environment->sshUrl);
    $remote_filepath = "/mnt/tmp/{$sitegroup}.{$environment->name}/" . basename($local_filepath);
    $this->logger->debug("Uploading database dump to $remote_filepath on remote machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
    $command = [
      'rsync',
      '-tDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $local_filepath,
      $environment->sshUrl . ':' . $remote_filepath,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL), NULL);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Could not upload local database dump: {message}',
        ['message' => $process->getOutput()]);
    }

    return $remote_filepath;
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   * @param string $remote_dump_filepath
   * @param \AcquiaCloudApi\Response\DatabaseResponse $database
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function importDatabaseDumpOnRemote($environment, $remote_dump_filepath, $database): void {
    $this->logger->debug("Importing $remote_dump_filepath to MySQL on remote machine");
    $command = "pv $remote_dump_filepath --bytes --rate | gunzip | MYSQL_PWD={$database->password} mysql --host={$this->getHostFromDatabaseResponse($environment, $database)} --user={$database->user_name} {$this->getNameFromDatabaseResponse($database)}";
    $process = $this->sshHelper->executeCommand($environment, [$command], ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL), NULL);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to import database on remote machine. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

  /**
   * @param $database
   *
   * @return string
   */
  protected function getNameFromDatabaseResponse($database): string {
    $db_url_parts = explode('/', $database->url);
    $db_name = end($db_url_parts);

    return $db_name;
  }

}
