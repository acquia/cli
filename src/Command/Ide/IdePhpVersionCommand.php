<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdePhpVersionCommand.
 */
class IdePhpVersionCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:php-version';

  /**
   * @var string
   */
  private string $idePhpFilePathPrefix;

  /*
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Change the PHP version in the current IDE')
      ->addArgument('version', InputArgument::REQUIRED, 'The PHP version')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();
    $version = $input->getArgument('version');
    $this->validatePhpVersion($version);
    $this->localMachineHelper->getFilesystem()->dumpFile($this->getIdePhpVersionFilePath(), $version);
    $this->restartService('php-fpm');

    return 0;
  }

  /**
   * @return string
   */
  private function getIdePhpFilePathPrefix(): string {
    if (!isset($this->idePhpFilePathPrefix)) {
      $this->idePhpFilePathPrefix = '/usr/local/php';
    }
    return $this->idePhpFilePathPrefix;
  }

  /**
   * @param string $path
   */
  public function setIdePhpFilePathPrefix(string $path): void {
    $this->idePhpFilePathPrefix = $path;
  }

  /**
   * {inheritdoc}.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validatePhpVersion(string $version): string {
    parent::validatePhpVersion($version);
    $php_filepath = $this->getIdePhpFilePathPrefix() . $version;
    if (!$this->localMachineHelper->getFilesystem()->exists($php_filepath)) {
      throw new AcquiaCliException('The specified PHP version does not exist on this machine.');
    }

    return $version;
  }

}
