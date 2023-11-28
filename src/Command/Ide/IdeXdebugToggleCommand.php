<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ide:xdebug-toggle', 'Toggle Xdebug on or off in the current IDE', ['xdebug'])]
class IdeXdebugToggleCommand extends IdeCommandBase {

  private ?bool $xDebugEnabled;

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function configure(): void {
    $this
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

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

    return Command::SUCCESS;
  }

  /**
   * Sets $this->xDebugEnabled.
   *
   * @param string $contents The contents of php.ini.
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

  private function getXDebugStatus(): ?bool {
    return $this->xDebugEnabled;
  }

  /**
   * Enables xDebug.
   *
   * @param string $contents The contents of php.ini.
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
   * @param string $contents The contents of php.ini.
   */
  private function disableXDebug(string $destinationFile, string $contents): void {
    $this->logger->notice("Disabling Xdebug PHP extension in $destinationFile...");
    $newContents = preg_replace('/(;)*(zend_extension=xdebug\.so)/', ';$2', $contents);
    file_put_contents($destinationFile, $newContents);
    $this->output->writeln("<info>Xdebug PHP extension disabled.</info>");
  }

}
