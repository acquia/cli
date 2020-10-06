<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeXdebugCommand.
 */
class IdeXdebugToggleCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:xdebug-toggle';

  /**
   * @var boolean|null
   */
  private $xDebugEnabled;

  /**
   * @var string
   */
  private $xdebugIniFilepath;

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Toggle xDebug on or off in the current IDE')
      ->setAliases(['xdebug'])
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->requireCloudIdeEnvironment();
    $ini_file = $this->getXdebugIniFilePath();
    $contents = file_get_contents($ini_file);
    $this->setXDebugStatus($contents);

    if ($this->getXDebugStatus() === FALSE) {
      $this->enableXDebug($ini_file, $contents);
      $this->restartService('php-fpm');
    } elseif ($this->getXDebugStatus() === TRUE) {
      $this->disableXDebug($ini_file, $contents);
      $this->restartService('php-fpm');
    } else {
      $this->logger->error("Could not find xdebug zend extension in $ini_file!");
    }

    return 0;
  }

  /**
   * @param string $file_path
   */
  public function setXdebugIniFilepath($file_path): void {
    $this->xdebugIniFilepath = $file_path;
  }

  /**
   * @return string
   */
  public function getXdebugIniFilePath(): string {
    if (!isset($this->xdebugIniFilepath)) {
      $this->xdebugIniFilepath = '/home/ide/configs/php/xdebug.ini';
    }
    return $this->xdebugIniFilepath;
  }

  /**
   * Sets $this->xDebugEnabled.
   *
   * @param string $contents
   *   The contents of php.ini.
   */
  protected function setXDebugStatus($contents): void {
    if (strpos($contents, ';zend_extension=xdebug.so') !== FALSE) {
      $this->xDebugEnabled = FALSE;
    } elseif (strpos($contents, 'zend_extension=xdebug.so') !== FALSE) {
      $this->xDebugEnabled = TRUE;
    } else {
      $this->xDebugEnabled = NULL;
    }
  }

  /**
   * Gets $this->xDebugEnabled.
   *
   * @return mixed
   *   $this->xDebugEnabled.
   */
  protected function getXDebugStatus() {
    return $this->xDebugEnabled;
  }

  /**
   * Enables xDebug.
   *
   * @param string $destination_file
   * @param string $contents
   *   The contents of php.ini.
   */
  protected function enableXDebug($destination_file, $contents): void {
    $this->logger->notice("Enabling xdebug in $destination_file...");
    // Note that this replaces 1 or more ";" characters.
    $new_contents = preg_replace('/(;)+(zend_extension=xdebug\.so)/', '$2', $contents);
    file_put_contents($destination_file, $new_contents);
    $this->output->writeln("<info>xDebug enabled.</info>");
  }

  /**
   * Disables xDebug.
   *
   * @param string $destination_file
   * @param string $contents
   *   The contents of php.ini.
   */
  protected function disableXDebug($destination_file, $contents) {
    $this->logger->notice("Disabling xdebug in $destination_file...");
    $new_contents = preg_replace('/(;)*(zend_extension=xdebug\.so)/', ';$2', $contents);
    file_put_contents($destination_file, $new_contents);
    $this->output->writeln("<info>xDebug disabled.</info>");
  }

}
