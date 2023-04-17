<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\DocsCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class DocsCommandTest.
 *
 * @property \Acquia\Cli\Command\DocsCommandTest $command
 * @package Acquia\Cli\Tests\Commands
 */
class DocsCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(DocsCommand::class);
  }

  /**
   * Tests the 'docs' command for Acquia Products.
   *
   * @dataProvider providerTestDocsCommand
   */
  public function testDocsCommand($input, $expectedOutput): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $local_machine_helper->startBrowser(Argument::any())->shouldBeCalled();
    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand([], [$input]);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Select the Acquia Product [Acquia CLI]:', $output);
    $this->assertStringContainsString($expectedOutput, $output);
  }

  public function providerTestDocsCommand(): array {
    return [
      [
        0,
        '[0 ] Acquia CLI',
      ],
      [
        1,
        '[1 ] Acquia CMS',
      ],
      [
        2,
        '[2 ] Code Studio',
      ],
      [
        3,
        '[3 ] Campaign Studio',
      ],
      [
        4,
        '[4 ] Content Hub',
      ],
      [
        5,
        '[5 ] Acquia Migrate Accelerate',
      ],
      [
        6,
        '[6 ] Site Factory',
      ],
      [
        7,
        '[7 ] Site Studio',
      ],
      [
        8,
        '[8 ] Edge',
      ],
      [
        9,
        '[9 ] Search',
      ],
      [
        10,
        '[10] Shield',
      ],
      [
        11,
        '[11] Customer Data Plateform',
      ],
      [
        12,
        '[12] Cloud IDE',
      ],
      [
        13,
        '[13] BLT',
      ],
      [
        14,
        '[14] Cloud Platform',
      ],
      [
        15,
        '[15] Acquia DAM Classic',
      ],
      [
        16,
        '[16] Personalization',
      ],
      [
        17,
        '[17] Campaign Factory',
      ],
    ];
  }

}
