<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * @property \Acquia\Cli\Command\App\UnlinkCommand $command
 */
class ClearCacheCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ClearCacheCommand::class);
    }

    #[Group('serial')]
    public function testAliasesAreCached(): void
    {
        ClearCacheCommand::clearCaches();
        $this->command = $this->injectCommand(IdeListCommand::class);

        // Request for applications.
        $applicationsResponse = self::getMockResponseFromSpec(
            '/applications',
            'get',
            '200'
        );
        $applicationsResponse = $this->filterApplicationsResponse($applicationsResponse, 1, true);
        $this->clientProphecy->request('get', '/applications')
            ->willReturn($applicationsResponse->{'_embedded'}->items)
            // Ensure this is only called once, even though we execute the command twice.
            ->shouldBeCalledTimes(1);

        $this->clientProphecy->addQuery('filter', 'hosting=@*:devcloud2')
            ->shouldBeCalled();
        $this->mockApplicationRequest();
        $this->mockRequest('getApplicationIdes', 'a47ac10b-58cc-4372-a567-0e02b2c3d470');
        $this->mockRequest('getAccount');

        $alias = 'devcloud2';
        $args = ['applicationUuid' => $alias];
        $inputs = [
            // Would you like to link the Cloud application Sample application to this repository?
            'n',
        ];

        $this->executeCommand($args, $inputs);
        // Run it twice, make sure API calls are made only once.
        $this->executeCommand($args, $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
    }

    public function testClearCaches(): void
    {
        // Seed both caches so we can prove each is cleared.
        $aliasCache = CommandBase::getAliasCache();
        $aliasItem = $aliasCache->getItem('some-alias');
        $aliasCache->save($aliasItem->set('value'));
        $updateCache = CommandBase::getUpdateCheckCache();
        $updateItem = $updateCache->getItem('latest-version.1_0_0');
        $updateCache->save($updateItem->set('2.0.0'));

        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Acquia CLI caches were cleared', $output);

        $this->assertCount(0, iterator_to_array($aliasCache->getItems(), false));
        $this->assertFalse(CommandBase::getUpdateCheckCache()->getItem('latest-version.1_0_0')->isHit());
    }
}
