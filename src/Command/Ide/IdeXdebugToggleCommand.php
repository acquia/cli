<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IdeXdebugToggleCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:xdebug-toggle';

  private ?bool $xDebugEnabled;

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function configure(): void {
    $this->setDescription('Toggle Xdebug on or off in the current IDE')
      ->setAliases(['xdebug'])
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();
    $iniFile = $this->getXdebugIniFilePath();
    $contents = file_get_contents($iniFile);
    $this->setXDebugStatus($contents);

    if ($this->getXDebugStatus() === FALSE) {
      $this->enableXDebug($iniFile, $contents);
    }
    elseif ($this->getXDebugStatus() === TRUE) {
      $this->disableXDebug($iniFile, $contents);
    }
    else {
      throw new AcquiaCliException("Could not find xdebug zend extension in $iniFile!");
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
  private function enableXDebug(string $destinationFile, string $contents): void {
    $this->logger->notice("Enabling Xdebug PHP extension in $destinationFile...");

    // Note that this replaces 1 or more ";" characters.
    $newContents = preg_replace('/(;)+(zend_extension=xdebug\.so)/', '$2', $contents);
    file_put_contents($destinationFile, $newContents);
    $this->output->writeln("<info>Xdebug PHP extension enabled.</info>");
    $this->output->writeln("You must also enable Xdebug listening in your code editor to begin a debugging session.");
  }

  /**
   * Disables xDebug.
   *
   * @param string $contents
   *   The contents of php.ini.
   */
  private function disableXDebug(string $destinationFile, string $contents): void {
    $this->logger->notice("Disabling Xdebug PHP extension in $destinationFile...");
    $newContents = preg_replace('/(;)*(zend_extension=xdebug\.so)/', ';$2', $contents);
    file_put_contents($destinationFile, $newContents);
    $this->output->writeln("<info>Xdebug PHP extension disabled.</info>");
  }

}
