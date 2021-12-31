<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class MySqlHelper {

  /**
   * @var string
   */
  protected string $localDbUser;

  /**
   * @var string
   */
  protected string $localDbPassword;

  /**
   * @var string
   */
  protected string $localDbName;

  /**
   * @var string
   */
  protected string $localDbHost;

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  private LocalMachineHelper $localMachineHelper;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  private InputInterface $input;

  /**
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   */
  public function __construct(
    LocalMachineHelper $local_machine_helper,
    InputInterface $input,
    OutputInterface $output,
    LoggerInterface $logger
  ) {
    $this->localMachineHelper = $local_machine_helper;
    $this->input = $input;
    $this->output = $output;
    $this->io = new SymfonyStyle($input, $output);
    $this->logger = $logger;
  }

  /**
   *
   */
  protected function setLocalDbUser(): void {
    $this->localDbUser = 'drupal';
    if ($lando_info = LandoHelper::getLandoInfo()) {
      $this->localDbUser = $lando_info->database->creds->user;
    }
    if (getenv('ACLI_DB_USER')) {
      $this->localDbUser = getenv('ACLI_DB_USER');
    }
  }

  /**
   * @return string
   */
  public function getDefaultLocalDbUser() {
    if (!isset($this->localDbUser)) {
      $this->setLocalDbUser();
    }

    return $this->localDbUser;
  }

  /**
   *
   */
  protected function setLocalDbPassword(): void {
    $this->localDbPassword = 'drupal';
    if ($lando_info = LandoHelper::getLandoInfo()) {
      $this->localDbPassword = $lando_info->database->creds->password;
    }
    if (getenv('ACLI_DB_PASSWORD')) {
      $this->localDbPassword = getenv('ACLI_DB_PASSWORD');
    }
  }

  /**
   * @return mixed
   */
  public function getDefaultLocalDbPassword() {
    if (!isset($this->localDbPassword)) {
      $this->setLocalDbPassword();
    }

    return $this->localDbPassword;
  }

  /**
   *
   */
  protected function setLocalDbName(): void {
    $this->localDbName = 'drupal';
    if ($lando_info = LandoHelper::getLandoInfo()) {
      $this->localDbName = $lando_info->database->creds->database;
    }
    if (getenv('ACLI_DB_NAME')) {
      $this->localDbName = getenv('ACLI_DB_NAME');
    }
  }

  /**
   * @return mixed
   */
  public function getDefaultLocalDbName() {
    if (!isset($this->localDbName)) {
      $this->setLocalDbName();
    }

    return $this->localDbName;
  }

  protected function setLocalDbHost(): void {
    $this->localDbHost = 'localhost';
    if ($lando_info = LandoHelper::getLandoInfo()) {
      $this->localDbHost = $lando_info->database->hostnames[0];
    }
    if (getenv('ACLI_DB_HOST')) {
      $this->localDbHost = getenv('ACLI_DB_HOST');
    }
  }

  /**
   * @return mixed
   */
  public function getDefaultLocalDbHost() {
    if (!isset($this->localDbHost)) {
      $this->setLocalDbHost();
    }

    return $this->localDbHost;
  }

  /**
   * @param string $db_host
   * @param string $db_user
   * @param string $db_name
   * @param string $db_password
   * @param null $output_callback
   *
   * @return string
   * @throws \Exception
   */
  protected function createMySqlDumpOnLocal(string $db_host, string $db_user, string $db_name, string $db_password, $output_callback = NULL): string {
    $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump', 'gzip']);
    $filename = "acli-mysql-dump-{$db_name}.sql.gz";
    $local_temp_dir = sys_get_temp_dir();
    $local_filepath = $local_temp_dir . '/' . $filename;
    $this->logger->debug("Dumping MySQL database to $local_filepath on this machine");
    $this->localMachineHelper->checkRequiredBinariesExist(['mysqldump', 'gzip']);
    if ($output_callback) {
      $output_callback('out', "Dumping MySQL database to $local_filepath on this machine");
    }
    if ($this->localMachineHelper->commandExists('pv')) {
      $command = "MYSQL_PWD={$db_password} mysqldump --host={$db_host} --user={$db_user} {$db_name} | pv --rate --bytes | gzip -9 > $local_filepath";
    }
    else {
      $this->io->warning('Please install `pv` to see progress bar');
      $command = "MYSQL_PWD={$db_password} mysqldump --host={$db_host} --user={$db_user} {$db_name} | gzip -9 > $local_filepath";
    }

    $process = $this->localMachineHelper->executeFromCmd($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful() || $process->getOutput()) {
      throw new AcquiaCliException('Unable to create a dump of the local database. {message}', ['message' => $process->getErrorOutput()]);
    }

    return $local_filepath;
  }
}