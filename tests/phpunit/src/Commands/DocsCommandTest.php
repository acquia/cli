<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\DocsCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\DocsCommandTest $command
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
        '[2 ] Acquia DAM Classic',
      ],
      [
        3,
        '[3 ] Acquia Migrate Accelerate',
      ],
      [
        4,
        '[4 ] BLT',
      ],
      [
        5,
        '[5 ] Campaign Factory',
      ],
      [
        6,
        '[6 ] Campaign Studio',
      ],
      [
        7,
        '[7 ] Cloud IDE',
      ],
      [
        8,
        '[8 ] Cloud Platform',
      ],
      [
        9,
        '[9 ] Code Studio',
      ],
      [
        10,
        '[10] Content Hub',
      ],
      [
        11,
        '[11] Customer Data Platform',
      ],
      [
        12,
        '[12] Edge',
      ],
      [
        13,
        '[13] Personalization',
      ],
      [
        14,
        '[14] Search',
      ],
      [
        15,
        '[15] Shield',
      ],
      [
        16,
        '[16] Site Factory',
      ],
      [
        17,
        '[17] Site Studio',
      ],
    ];
  }

}
