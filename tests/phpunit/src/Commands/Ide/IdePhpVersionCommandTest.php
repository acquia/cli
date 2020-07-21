<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Class IdePhpVersionCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdePhpVersionCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdePhpVersionCommandTest extends IdeRequiredTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdePhpVersionCommand::class);
  }

  /**
   * @return array
   */
  public function providerTestIdePhpVersionCommand(): array {
    return [
      ['7.2'],
      ['7.3'],
    ];
  }

  /**
   * Tests the 'ide:php-version' command.
   *
   * @dataProvider providerTestIdePhpVersionCommand
   *
   * @param string $version
   *
   * @throws \Exception
   */
  public function testIdePhpVersionCommand($version): void {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper
      ->execute([
        'supervisorctl',
        'restart',
        'php-fpm',
      ], NULL, NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    // Set up file system.
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $php_finder = new PhpExecutableFinder();
    $actual_php_path = dirname($php_finder->find());
    $this->command->setPhpBinPath($actual_php_path);
    $this->command->setPhpVersionFilePath($this->fs->tempnam(sys_get_temp_dir(), 'acli_php_version_file_'));
    $this->executeCommand([
      'version' => $version,
    ], []);
    $this->assertEquals($version, getenv('PHP_VERSION'));
    $this->assertStringContainsString($actual_php_path, getenv('PATH'));
    $this->assertFileExists($this->command->getPhpVersionFilePath());
    $this->assertEquals($version, file_get_contents($this->command->getPhpVersionFilePath()));
  }

  /**
   * @return array
   */
  public function providerTestIdePhpVersionCommandFailure(): array {
    return [
      ['6.3', AcquiaCliException::class],
      ['6', ValidatorException::class],
      ['7', ValidatorException::class],
      ['7.', ValidatorException::class],
    ];
  }

  /**
   * Tests the 'ide:php-version' command.
   *
   * @dataProvider providerTestIdePhpVersionCommandFailure
   *
   * @param string $version
   * @param string $exception_class
   */
  public function testIdePhpVersionCommandFailure($version, $exception_class): void {
    try {
      $this->executeCommand([
        'version' => $version,
      ], []);
    }
    catch (\Exception $exception) {
      $this->assertEquals($exception_class, get_class($exception));
    }
  }

}
