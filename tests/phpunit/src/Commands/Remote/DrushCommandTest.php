<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\DrushCommand;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class DrushCommandTest.
 *
 * @property DrushCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class DrushCommandTest extends SshCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return new DrushCommand();
  }

  /**
   * Tests the 'remote:drush' commands.
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   */
  public function testRemoteDrushCommand(): void {
    $this->setCommand($this->createCommand());
    $this->mockForGetEnvironmentFromAliasArg();
    [$process, $local_machine_helper] = $this->mockForExecuteCommand();

    $ssh_command = [
      'ssh',
      'site.dev@server-123.hosted.hosting.acquia.com',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
      'cd /var/www/html/devcloud2.dev/docroot; ',
      'drush',
      'status',
    ];
    $local_machine_helper
      ->execute($ssh_command, Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->application->setLocalMachineHelper($local_machine_helper->reveal());

    $args = [
      'alias' => 'devcloud2.dev',
      'drush_command' => 'status',
      '-vvv' => '',
    ];
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
