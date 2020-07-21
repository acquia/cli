<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
  private $phpBinPath;

  /**
   * @var string
   */
  private $phpVersionFilePath;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Change the PHP version in the current IDE')
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
    $this->requireCloudIdeEnvironment();
    $version = $input->getArgument('version');
    $this->validatePhpVersion($version);
    $this->localMachineHelper->getFilesystem()->dumpFile($this->getPhpVersionFilePath(), $version);
    putenv('PHP_VERSION=' . $version);
    $path = $this->getPhpBinPath($version) . ':' . getenv('PATH');
    putenv("PATH=\"{$path}\"");
    $this->restartPhp();

    return 0;
  }

  /**
   * @return string
   */
  public function getPhpVersionFilePath(): string {
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
   * @return string
   */
  public function getPhpBinPath($version): string {
    if (!isset($this->phpBinPath)) {
      $this->phpBinPath = '/usr/local/php' . $version . '/bin';
    }
    return $this->phpBinPath;
  }

  /**
   * @param string $path
   */
  public function setPhpBinPath($path): void {
    $this->phpBinPath = $path;
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
    if (!$this->localMachineHelper->getFilesystem()->exists($this->getPhpVersionFilePath())) {
      throw new AcquiaCliException('The specified PHP version does not exist on this machine.');
    }

    return $version;
  }

}
