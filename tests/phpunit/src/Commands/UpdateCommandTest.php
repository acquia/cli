<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\UpdateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use drupol\phposinfo\OsInfo;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

/**
 * Class UpdateCommandTest.
 *
 * @property \Acquia\Cli\Command\UpdateCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class UpdateCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new UpdateCommand();
  }

  public function testNonPharException(): void {
    $this->setCommand($this->createCommand());
    try {
      $this->executeCommand([], []);
    }
    catch (Exception $e) {
      $this->assertStringContainsString('update only works when running the phar version of ', $e->getMessage());
    }
  }

  /**
   * @requires OS linux|darwin
   */
  public function testDownloadUpdate(): void {
    $this->setCommand($this->createCommand());
    $stub_phar = $this->fs->tempnam(sys_get_temp_dir(), 'acli_phar');
    $this->fs->chmod($stub_phar, 0751);
    $original_file_perms = fileperms($stub_phar);
    $this->command->setPharPath($stub_phar);

    $args = [
      '--allow-unstable' => '',
    ];
    $this->executeCommand($args, []);

    $output = $this->getDisplay();
    $this->assertEquals($this->getStatusCode(), 0);
    $this->assertStringContainsString('Updated from UNKNOWN to', $output);
    $this->assertFileExists($stub_phar);

    // The file permissions on the new phar should be the same as on the old phar.
    $this->assertEquals($original_file_perms, fileperms($stub_phar) );

    // Execute it.
    $process = new Process([$stub_phar]);
    $output = $process->mustRun()->getOutput();
    $this->assertStringContainsString('Available commands:', $output);
  }

  /**
   * @return string
   */
  protected function createPharStub(): string {
    $stub_phar = $this->fs->tempnam(sys_get_temp_dir(), 'acli_phar');
    $this->fs->chmod($stub_phar, 0751);
    $this->command->setPharPath($stub_phar);
    return $stub_phar;
  }

}
