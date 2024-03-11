<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Remote;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Remote\DrushCommand;
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
          'drush_command' => 'status --fields=db-status',
        ],
      ],
      [
        [
          '-vvv' => '',
          'drush_command' => 'status --fields=db-status',
        ],
      ],
    ];
  }

  /**
   * @dataProvider providerTestRemoteDrushCommand
   * @group serial
   */
  public function testRemoteDrushCommand(array $args): void {
    $this->mockGetEnvironment();
    [$process, $localMachineHelper] = $this->mockForExecuteCommand();
    $localMachineHelper->checkRequiredBinariesExist(['ssh'])->shouldBeCalled();
    $sshCommand = [
      'ssh',
      'site.dev@sitedev.ssh.hosted.acquia-sites.com',
      '-t',
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
      '-o LogLevel=ERROR',
      'cd /var/www/html/site.dev/docroot; ',
      'drush',
      '--uri=http://sitedev.hosted.acquia-sites.com status --fields=db-status',
    ];
    $localMachineHelper
      ->execute($sshCommand, Argument::type('callable'), NULL, TRUE, NULL)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $this->command->sshHelper = new SshHelper($this->output, $localMachineHelper->reveal(), $this->logger);
    $this->executeCommand($args, self::inputChooseEnvironment());

    // Assert.
    $this->getDisplay();
  }

}
