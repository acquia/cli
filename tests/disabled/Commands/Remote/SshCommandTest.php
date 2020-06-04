<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\SshCommand;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class SshCommandTest.
 *
 * @property SshCommand $command
 * @package Acquia\Cli\Tests\Remote
 */
class SshCommandTest extends SshCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(SshCommand::class);
  }

  /**
   * Tests the 'remote:ssh' commands.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRemoteAliasesDownloadCommand(): void {

    $this->mockForGetEnvironmentFromAliasArg();
    [$process, $local_machine_helper] = $this->mockForExecuteCommand();
    $ssh_command = [
      'ssh',
      'site.dev@server-123.hosted.hosting.acquia.com',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
    ];
    $local_machine_helper
      ->execute($ssh_command, Argument::type('callable'), NULL, TRUE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->localMachineHelper = $local_machine_helper->reveal();

    $args = [
      'alias' => 'devcloud2.dev',
    ];
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
