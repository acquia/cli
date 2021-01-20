<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\SshCommand;
use Acquia\Cli\Helpers\SshHelper;
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
      'site.dev@sitedev.ssh.hosted.acquia-sites.com',
      '-t',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
      'cd /var/www/html/devcloud2.dev; exec $SHELL -l'
    ];
    $local_machine_helper
      ->execute($ssh_command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = new SshHelper($this->output, $local_machine_helper->reveal());

    $args = [
      'alias' => 'devcloud2.dev',
    ];
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
  }

}
