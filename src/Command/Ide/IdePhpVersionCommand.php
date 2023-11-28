<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ide:php-version', description: 'Change the PHP version in the current IDE')]
final class IdePhpVersionCommand extends IdeCommandBase {

  private string $idePhpFilePathPrefix;

  protected function configure(): void {
    $this
      ->addArgument('version', InputArgument::REQUIRED, 'The PHP version')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();
    $version = $input->getArgument('version');
    $this->validatePhpVersion($version);
    $this->localMachineHelper->getFilesystem()->dumpFile($this->getIdePhpVersionFilePath(), $version);
    $this->restartService('php-fpm');

    return Command::SUCCESS;
  }

  private function getIdePhpFilePathPrefix(): string {
    if (!isset($this->idePhpFilePathPrefix)) {
      $this->idePhpFilePathPrefix = '/usr/local/php';
    }
    return $this->idePhpFilePathPrefix;
  }

  public function setIdePhpFilePathPrefix(string $path): void {
    $this->idePhpFilePathPrefix = $path;
  }

  protected function validatePhpVersion(string $version): string {
    parent::validatePhpVersion($version);
    $phpFilepath = $this->getIdePhpFilePathPrefix() . $version;
    if (!$this->localMachineHelper->getFilesystem()->exists($phpFilepath)) {
      throw new AcquiaCliException('The specified PHP version does not exist on this machine.');
    }

    return $version;
  }

}
