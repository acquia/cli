<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Class IdePhpVersionCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\IdePhpVersionCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdePhpVersionCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

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
      ['7.4'],
      ['8.0'],
      ['8.1'],
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
  public function testIdePhpVersionCommand(string $version): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->mockRestartPhp($local_machine_helper);
    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);
    $mock_file_system->copy(Argument::type('string'), '/home/ide/configs/php/xdebug.ini', TRUE)->willReturn(TRUE);
    $php_filepath_prefix = $this->fs->tempnam(sys_get_temp_dir(), 'acli_php_stub_');
    $php_stub_filepath = $php_filepath_prefix . $version;
    $mock_file_system->exists($php_stub_filepath)->willReturn(TRUE);
    $php_version_file_path = $this->fs->tempnam(sys_get_temp_dir(), 'acli_php_version_file_');
    $mock_file_system->dumpFile($php_version_file_path, $version)->shouldBeCalled();

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->setPhpVersionFilePath($php_version_file_path);
    $this->command->setIdePhpFilePathPrefix($php_filepath_prefix);
    $this->executeCommand([
      'version' => $version,
    ], []);
    $this->prophet->checkPredictions();
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
    catch (Exception $exception) {
      $this->assertEquals($exception_class, get_class($exception));
    }
  }

  /**
   * Tests the 'ide:php-version' command outside of IDE environment.
   */
  public function testIdePhpVersionCommandOutsideIde(): void {
    $this->unsetCloudIdeEnvVars();
    try {
      $this->executeCommand([
        'version' => '7.3',
      ], []);
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('This command can only be run inside of an Acquia Cloud IDE', $exception->getMessage());
    }
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockRestartPhp(ObjectProphecy $local_machine_helper): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $local_machine_helper->execute([
        'supervisorctl',
        'restart',
        'php-fpm',
      ], NULL, NULL, FALSE)->willReturn($process->reveal())->shouldBeCalled();
    return $process;
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockGetFilesystem(ObjectProphecy $local_machine_helper) {
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper->getFilesystem()->willReturn($file_system)->shouldBeCalled();

    return $file_system;
  }

}
