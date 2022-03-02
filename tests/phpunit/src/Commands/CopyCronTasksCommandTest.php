<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\CopyCronTasksCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class CopyCronTasksCommandTest.
 *
 * @property \Acquia\Cli\Command\CopyCronTasksCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class CopyCronTasksCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(CopyCronTasksCommand::class);
  }

  /**
   * Tests the 'app:cron-copy' command.
   *
   * @throws \Exception
   */
  public function testCopyCronTasksCommandTest(): void {
    $environments_response = $this->getMockEnvironmentsResponse();
    $source_crons_list_response = $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'get', '200');
    $this->clientProphecy->request('get',
      '/environments/' . $environments_response->{'_embedded'}->items[0]->id . '/crons')
      ->willReturn($source_crons_list_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $create_cron_response = $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'post', '202');
    $this->clientProphecy->request('post',
      '/environments/' . $environments_response->{'_embedded'}->items[2]->id . '/crons', Argument::type('array'))
      ->willReturn($create_cron_response->{'Adding cron'}->value)
      ->shouldBeCalled();

    $source = '24-a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $dest = '32-a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $this->executeCommand(
      [
      'source_app' => $source,
      'dest_app' => $dest,
      ],
      [
        'y'
      ]
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('Are you sure you\'d like to copy the cron jobs from ' . $source . ' to ' . $dest . '? (yes/no) [yes]:', $output);
    $this->assertStringContainsString('Copying the cron task "" from ' . $source . ' to ' . $dest, $output);
    $this->assertStringContainsString('[OK] Cron task copy is completed.', $output);
  }

  /**
   * Tests the 'app:cron-copy' command fail.
   *
   * @throws \Exception
   */
  public function testCopyCronTasksCommandTestFail(): void {
    $this->executeCommand([
        'source_app' => 'app.test',
        'dest_app' => 'app.test'
      ],
    );
    $output = $this->getDisplay();
    $this->assertStringContainsString('The source and destination environments can not be same', $output);
  }

}
