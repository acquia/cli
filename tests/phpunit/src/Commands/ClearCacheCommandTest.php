<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\ClearCacheCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class ClearCacheCommandTest.
 *
 * @property \Acquia\Cli\Command\UnlinkCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class ClearCacheCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  public function testAliasesAreCached(): void {
    $this->command = $this->injectCommand(IdeListCommand::class);

    // Request for applications.
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $this->clientProphecy->request('get', '/applications')
      ->willReturn($applications_response->{'_embedded'}->items)
      // Ensure this is only called once, even though we execute the command twice.
      ->shouldBeCalledTimes(1);

    $this->clientProphecy->addQuery('filter', 'hosting=@*devcloud2')->shouldBeCalled();
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();

    $alias = 'devcloud2';
    $this->clientProphecy->clearQuery()->shouldBeCalled();
    $args = ['applicationUuid' => $alias];
    $inputs = [
      // Would you like to link the Cloud application Sample application to this repository?
      'n'
    ];

    $this->executeCommand($args, $inputs);
    // Run it twice, make sure API calls are made only once.
    $this->executeCommand($args, $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
  }

  /**
   * Tests the 'clear-caches' command.
   *
   * @throws \Exception
   */
  public function testClearCaches(): void {
    $this->executeCommand([], []);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Acquia CLI caches were cleared', $output);

    $cache = CommandBase::getAliasCache();
    $this->assertCount(0, $cache->getItems());
  }

}
