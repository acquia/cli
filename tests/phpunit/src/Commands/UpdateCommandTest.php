<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\UnlinkCommand;
use Acquia\Cli\Command\UpdateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

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
    catch (\Exception $e) {
      $this->assertStringContainsString('update only works when running the phar version of ', $e->getMessage());
    }
  }

  public function testUpdate() {
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
    $output = shell_exec($stub_phar);
    $this->assertStringContainsString('Available commands:', $output);
  }

}
