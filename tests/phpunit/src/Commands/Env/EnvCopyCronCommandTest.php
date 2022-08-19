<?php

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\Env\EnvCopyCronCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;

/**
 * Class CopyCronTasksCommandTest.
 *
 * @property \Acquia\Cli\Command\Env\EnvCopyCronCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class EnvCopyCronCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(EnvCopyCronCommand::class);
  }

  /**
   * Tests the 'app:cron-copy' command.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testCopyCronTasksCommandTest(): void {
    $environments_response = $this->getMockEnvironmentsResponse();
    $source_crons_list_response = $this->getMockResponseFromSpec('/environments/{environmentId}/crons', 'get', '200');
    // todo: remove hack for missing label (CXAPI-9746)
    $source_crons_list_response->_embedded->items[0]->label = 'foo';
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
      'source_env' => $source,
      'dest_env' => $dest,
      ],
      [
        'y'
      ]
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('Are you sure you\'d like to copy the cron jobs from ' . $source . ' to ' . $dest . '? (yes/no) [yes]:', $output);
    $this->assertStringContainsString('Copying the cron task "foo" from ' . $source . ' to', $output);
    $this->assertStringContainsString($dest, $output);
    $this->assertStringContainsString('[OK] Cron task copy is completed.', $output);
  }

  /**
   * Tests the 'app:cron-copy' command fail.
   *
   * @throws \Exception
   */
  public function testCopyCronTasksCommandTestFail(): void {
    $this->executeCommand([
        'source_env' => 'app.test',
        'dest_env' => 'app.test'
      ],
    );
    $output = $this->getDisplay();
    $this->assertStringContainsString('The source and destination environments can not be same', $output);
  }

}
