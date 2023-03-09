<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeXdebugCommand.
 */
class IdeXdebugToggleCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:xdebug-toggle';

  private ?bool $xDebugEnabled;

  /**
   *
   */
  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Toggle Xdebug on or off in the current IDE')
      ->setAliases(['xdebug'])
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();
    $ini_file = $this->getXdebugIniFilePath();
    $contents = file_get_contents($ini_file);
    $this->setXDebugStatus($contents);

    if ($this->getXDebugStatus() === FALSE) {
      $this->enableXDebug($ini_file, $contents);
    }
    elseif ($this->getXDebugStatus() === TRUE) {
      $this->disableXDebug($ini_file, $contents);
    }
    else {
      throw new AcquiaCliException("Could not find xdebug zend extension in $ini_file!");
    }
    $this->restartService('php-fpm');

    return 0;
  }

  /**
   * Sets $this->xDebugEnabled.
   *
   * @param string $contents
   *   The contents of php.ini.
   */
  private function setXDebugStatus(string $contents): void {
    if (str_contains($contents, ';zend_extension=xdebug.so')) {
      $this->xDebugEnabled = FALSE;
    }
    elseif (str_contains($contents, 'zend_extension=xdebug.so')) {
      $this->xDebugEnabled = TRUE;
    }
    else {
      $this->xDebugEnabled = NULL;
    }
  }

  /**
   * Gets $this->xDebugEnabled.
   *
   * @return bool|null
   *   $this->xDebugEnabled.
   */
  private function getXDebugStatus(): ?bool {
    return $this->xDebugEnabled;
  }

  /**
   * Enables xDebug.
   *
   * @param string $contents
   *   The contents of php.ini.
   */
  private function enableXDebug(string $destination_file, string $contents): void {
    $this->logger->notice("Enabling Xdebug PHP extension in $destination_file...");

    // Note that this replaces 1 or more ";" characters.
    $new_contents = preg_replace('/(;)+(zend_extension=xdebug\.so)/', '$2', $contents);
    file_put_contents($destination_file, $new_contents);
    $this->output->writeln("<info>Xdebug PHP extension enabled.</info>");
    $this->output->writeln("You must also enable Xdebug listening in your code editor to begin a debugging session.");
  }

  /**
   * Disables xDebug.
   *
   * @param string $contents
   *   The contents of php.ini.
   */
  private function disableXDebug(string $destination_file, string $contents): void {
    $this->logger->notice("Disabling Xdebug PHP extension in $destination_file...");
    $new_contents = preg_replace('/(;)*(zend_extension=xdebug\.so)/', ';$2', $contents);
    file_put_contents($destination_file, $new_contents);
    $this->output->writeln("<info>Xdebug PHP extension disabled.</info>");
  }

}
