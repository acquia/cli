<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeXdebugCommand.
 */
class IdeXdebugCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:xdebug';

  /** @var boolean|null */
  private $xDebugEnabled;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Toggle xDebug on or off in the current IDE')
      ->addArgument('version', InputArgument::REQUIRED, 'The PHP version')
      ->setHidden(AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $ini_file = '/home/ide/configs/php/xdebug.ini';
    $contents = $this->localMachineHelper->readFile($ini_file);
    $this->setXDebugStatus($contents);

    if ($this->getXDebugStatus() === FALSE) {
      $this->enableXDebug($ini_file, $contents);
      $this->restartPhp();
    } elseif ($this->getXDebugStatus() === TRUE) {
      $this->disableXDebug($ini_file, $contents);
      $this->restartPhp();
    } else {
      $this->logger->error("Could not find xdebug zend extension in $ini_file!");
    }

    return 0;
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
   * @param string $contents
   *   The contents of php.ini.
   */
  protected function enableXDebug($destination_file, $contents): void {
    $this->logger->notice("Enabling xdebug in $destination_file...");
    $new_contents = preg_replace('|(;)+(zend_extension=xdebug\.so)|', '$2', $contents);
    $this->localMachineHelper->writeFile($destination_file, $new_contents);
    $this->output->writeln("<info>xDebug enabled.</info>");
  }

  /**
   * Disables xDebug.
   *
   * @param string $contents
   *   The contents of php.ini.
   */
  protected function disableXDebug($destination_file, $contents) {
    $this->logger->notice("Disabling xdebug in $destination_file...");
    $new_contents = preg_replace('|(;)*(zend_extension=xdebug\.so)|', ';$2', $contents);
    $this->localMachineHelper->writeFile($destination_file, $new_contents);
    $this->output->writeln("<info>xDebug disabled.</info>");
  }

}
