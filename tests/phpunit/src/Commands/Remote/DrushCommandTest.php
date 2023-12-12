<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Remote\DrushCommand;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Helpers\SshHelper;
use Prophecy\Argument;

/**
 * @property DrushCommand $command
 */
class DrushCommandTest extends SshCommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(DrushCommand::class);
  }

  /**
   * @return array<array<array<string>>>
   */
  public function providerTestRemoteDrushCommand(): array {
    return [
      [
        [
          '-vvv' => '',
          'alias' => 'devcloud2.dev',
          'drush_command' => 'status --fields=db-status',
        ],
      ],
      [
        [
          '-vvv' => '',
          'alias' => '@devcloud2.dev',
          'drush_command' => 'status --fields=db-status',
        ],
      ],
    ];
  }

  /**
   * @dataProvider providerTestRemoteDrushCommand
   * @param array $args
   * @group serial
   */
  public function testRemoteDrushCommand(array $args): void {
    ClearCacheCommand::clearCaches();
    $this->mockForGetEnvironmentFromAliasArg();
    [$process, $localMachineHelper] = $this->mockForExecuteCommand();
    $localMachineHelper->checkRequiredBinariesExist(['ssh'])->shouldBeCalled();
    $sshCommand = [
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
    $localMachineHelper
      ->execute($sshCommand, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
    $this->executeCommand($args);

    // Assert.
    $this->prophet->checkPredictions();
    $this->getDisplay();
  }

}
