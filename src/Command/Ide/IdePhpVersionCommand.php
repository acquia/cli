<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class IdePhpVersionCommand.
 */
class IdePhpVersionCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:php-version';

  /**
   * @var string
   */
  private $phpVersionFilePath;

  /**
   * @var string
   */
  private $idePhpFilePathPrefix;

  /*
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
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->requireCloudIdeEnvironment();
    $version = $input->getArgument('version');
    $this->validatePhpVersion($version);
    $this->localMachineHelper->getFilesystem()->dumpFile($this->getIdePhpVersionFilePath(), $version);
    $this->restartService('php-fpm');
    $this->restartBash();

    return 0;
  }

  /**
   * @return string
   */
  public function getIdePhpFilePathPrefix(): string {
    if (!isset($this->idePhpFilePathPrefix)) {
      $this->idePhpFilePathPrefix = '/usr/local/php';
    }
    return $this->idePhpFilePathPrefix;
  }

  /**
   * @param string $path
   */
  public function setIdePhpFilePathPrefix($path): void {
    $this->idePhpFilePathPrefix = $path;
  }

  /**
   * @return string
   */
  public function getIdePhpVersionFilePath(): string {
    if (!isset($this->phpVersionFilePath)) {
      $this->phpVersionFilePath = '/home/ide/configs/php/.version';
    }
    return $this->phpVersionFilePath;
  }

  /**
   * @param string $path
   */
  public function setPhpVersionFilePath($path): void {
    $this->phpVersionFilePath = $path;
  }

  /**
   * @param string $version
   *
   * @return mixed
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validatePhpVersion($version) {
    $violations = Validation::createValidator()->validate($version, [
      new Length(['min' => 3]),
      new NotBlank(),
      new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces']),
      new Regex(['pattern' => '/[0-9]{1}\.[0-9]{1}/', 'message' => 'The value must be in the format "x.y"']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    $php_filepath = $this->getIdePhpFilePathPrefix() . $version;
    if (!$this->localMachineHelper->getFilesystem()->exists($php_filepath)) {
      throw new AcquiaCliException('The specified PHP version does not exist on this machine.');
    }

    return $version;
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function restartBash(): void {
    $this->logger->notice('Restarting bash...');
    $process = $this->localMachineHelper->executeFromCmd('exec bash -l', NULL, NULL, TRUE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Could not restart Bash');
    }
  }

}
