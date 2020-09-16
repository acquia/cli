<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\DrushCommand;
use Acquia\Cli\Helpers\SshHelper;
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
    return $this->injectCommand(DrushCommand::class);
  }

  public function providerTestRemoteDrushCommand(): array {
    return [
      [
        [
          'alias' => 'devcloud2.dev',
          'drush_command' => 'status --fields=db-status',
          '-vvv' => '',
        ],
      ],
      [
        [
          'alias' => '@devcloud2.dev',
          'drush_command' => 'status --fields=db-status',
          '-vvv' => '',
        ],
      ],
    ];
  }

  /**
   * Tests the 'remote:drush' commands.
   *
   * @dataProvider providerTestRemoteDrushCommand
   * @param array $args
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRemoteDrushCommand($args): void {

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
      'status --fields=db-status',
    ];
    $local_machine_helper
      ->execute($ssh_command, Argument::type('callable'), NULL, TRUE, 600)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = new SshHelper($this->output, $local_machine_helper->reveal());
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $this->getDisplay();
  }

}
