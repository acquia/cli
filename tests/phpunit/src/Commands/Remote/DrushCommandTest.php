<?php

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\Remote\DrushCommand;
use Acquia\Cli\Command\Self\ClearCacheCommand;
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
   * @group serial
   */
  public function testRemoteDrushCommand(array $args): void {
    ClearCacheCommand::clearCaches();
    $this->mockForGetEnvironmentFromAliasArg();
    [$process, $local_machine_helper] = $this->mockForExecuteCommand();
    $local_machine_helper->checkRequiredBinariesExist(['ssh'])->shouldBeCalled();
    $ssh_command = [
      'ssh',
      'site.dev@sitedev.ssh.hosted.acquia-sites.com',
      '-t',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
      'cd /var/www/html/devcloud2.dev/docroot; ',
      'drush',
      'status --fields=db-status',
    ];
    $local_machine_helper
      ->execute($ssh_command, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = new SshHelper($this->output, $local_machine_helper->reveal(), $this->logger);
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $this->getDisplay();
  }

}
