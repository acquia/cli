<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class ClearCacheCommandTest.
 *
 * @property \Acquia\Cli\Command\App\UnlinkCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class ClearCacheCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  /**
   * @throws \JsonException
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   * @group serial
   */
  public function testAliasesAreCached(): void {
    ClearCacheCommand::clearCaches();
    $this->command = $this->injectCommand(IdeListCommand::class);

    // Request for applications.
    $applications_response = $this->getMockResponseFromSpec('/applications',
      'get', '200');
    $applications_response = $this->filterApplicationsResponse($applications_response, 1, TRUE);
    $this->clientProphecy->request('get', '/applications')
      ->willReturn($applications_response->{'_embedded'}->items)
      // Ensure this is only called once, even though we execute the command twice.
      ->shouldBeCalledTimes(1);

    $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')->shouldBeCalled();
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();
    $this->mockAccountRequest();

    $alias = 'devcloud2';
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
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testClearCaches(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Acquia CLI caches were cleared', $output);

    $cache = CommandBase::getAliasCache();
    $this->assertCount(0, iterator_to_array($cache->getItems(), FALSE));
  }

}
