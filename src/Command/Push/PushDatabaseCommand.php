<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class PushDatabaseCommand.
 */
class PushDatabaseCommand extends PullCommandBase {

  protected static $defaultName = 'push:database';

  /**
   * @var string
   */
  protected $dir;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Push a database from your IDE to a Cloud Platform environment')
      ->setAliases(['push:db'])
      ->addOption('cloud-env-uuid', 'from', InputOption::VALUE_REQUIRED,
        'The UUID of the associated Cloud Platform source environment');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $destination_environment = $this->determineEnvironment($input, $output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $database = $this->determineSourceDatabase($acquia_cloud_client, $destination_environment);

    $question = new ConfirmationQuestion("<question>Overwrite the <bg=cyan;options=bold>{$database->name}</> database on <bg=cyan;options=bold>{$destination_environment->name}</> with a copy of the database from the current machine?</question> ", TRUE);
    $answer = $this->questionHelper->ask($this->input, $this->output, $question);
    if (!$answer) {
      return 0;
    }

    $this->checklist = new Checklist($output);
    $this->checklist->addItem('Creating local database dump');
    $local_dump_filepath = $this->createMySqlDumpOnLocal($this->localDbHost, $this->localDbUser, $this->localDbName, $this->localDbPassword);
    $this->checklist->completePreviousItem();
    $this->checklist->addItem('Uploading database dump to remote machine');
    $remote_dump_filepath = $this->uploadDatabaseDump($destination_environment, $database, $local_dump_filepath, $this->getOutputCallback($output, $this->checklist));
    $this->checklist->completePreviousItem();
    $this->checklist->addItem('Importing database dump into MySQL on remote machine');
    $this->importDatabaseDumpOnRemote($destination_environment, $remote_dump_filepath, $database);
    $this->checklist->completePreviousItem();

    return 0;
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param callable $output_callback
   *
   * @return string
   * @throws \Exception
   */
  protected function createMySqlDumpOnLocal($db_host, $db_user, $db_name, $db_password, $output_callback = NULL): string {
    $filename = "acli-mysql-dump-{$db_name}.sql.gz";
    $local_temp_dir = '/tmp';
    $local_filepath = $local_temp_dir . '/' . $filename;
    $this->logger->debug("Dumping MySQL database to $local_filepath on this machine");
    $command = "MYSQL_PWD={$db_password} mysqldump --host={$db_host} --user={$db_user} {$db_name} | pv --rate --bytes | gzip -9 > $local_filepath";
    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, $this->output->isVerbose());
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to create a dump of the local database. {message}', ['message' => $process->getErrorOutput()]);
    }

    return $local_filepath;
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
    $remote_filepath = '/mnt/tmp/' . $this->getNameFromDatabaseResponse($database) . '/' . basename($local_filepath);;
    $this->logger->debug("Uploading database dump to $remote_filepath on remote machine");
    $command = [
      'rsync',
      '-tDvPhe',
      'ssh -o StrictHostKeyChecking=no',
      $local_filepath,
      $environment->sshUrl . ':' . $remote_filepath,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, $this->output->isVerbose(), NULL);
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
    $command = "pv $remote_dump_filepath --bytes --rate | gunzip | MYSQL_PWD={$database->password} mysql --host={$this->getHostFromDatabaseResponse($database)} --user={$database->user_name} {$this->getNameFromDatabaseResponse($database)}";
    $process = $this->sshHelper->executeCommand($environment, [$command], $this->output->isVerbose(), NULL);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to import database on remote machine. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

}
