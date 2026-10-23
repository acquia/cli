<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Source\LinkCommand;
use Acquia\Cli\Command\Source\SourceCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;

/**
 * @property LinkCommand $command
 */
class LinkCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $command = $this->injectCommand(LinkCommand::class);
        $this->setCwd($command, $this->projectDir);
        return $command;
    }

    private function setCwd(CommandBase $command, string $cwd): void
    {
        (new ReflectionProperty(SourceCommandBase::class, 'cwd'))->setValue($command, $cwd);
    }

    private function mockSite(string $id): void
    {
        $this->clientProphecy->request('get', "/source-sites/$id")
            ->willReturn((object) ['id' => $id, 'label' => "Site $id"])
            ->shouldBeCalled();
    }

    public function testArgumentRecordsSite(): void
    {
        $this->mockSite('site-a');
        $this->executeCommand(['sourceSiteId' => 'site-a']);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->assertStringContainsString('Linked this working copy to Source site Site site-a (site-a) by writing to', $this->getDisplay());
        $this->assertStringContainsString($this->projectDir . '/.acquia-cli.yml', $this->getDisplay());
    }

    public function testArgumentOverwritesExistingLink(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->mockSite('site-b');
        $this->executeCommand(['sourceSiteId' => 'site-b']);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-b\n");
    }

    public function testInvalidArgumentThrowsBeforeRequesting(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('"site-a/../applications" is not a valid Source site ID: only letters, digits and hyphens are allowed.');
        $this->executeCommand(['sourceSiteId' => 'site-a/../applications']);
    }

    public function testEmptyArgumentThrowsRatherThanChoosing(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('"" is not a valid Source site ID: only letters, digits and hyphens are allowed.');
        $this->executeCommand(['sourceSiteId' => '']);
    }

    public function testAlreadyLinkedWithoutArgument(): void
    {
        file_put_contents($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
        $this->executeCommand();
        $this->assertSame(1, $this->getStatusCode());
        $this->assertStringContainsString('already linked to Source site site-a. Run acli source:cms:link <sourceSiteId> to link it to another site.', $this->getDisplay());
    }

    public function testNonInteractiveWithoutArgumentThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Pass the Source site ID as the sourceSiteId argument when running non-interactively.');
        $this->executeCommand([], [], interactive: false);
    }

    /**
     * Mocks GET /source-sites as the service pages it: one HAL response per
     * page, each with the pagination envelope that Client::request() drops.
     *
     * @param array<array<string>> $pages Site IDs per page.
     * @param int|null $total What the service claims; by default, the truth.
     */
    private function mockSitePages(array $pages, ?int $total = null): void
    {
        $total ??= array_sum(array_map('count', $pages));
        $limit = max(1, count($pages[0] ?? []));
        // What the real modifyOptions() returns: the headers acli configured.
        // Every page request must carry them, plus the page size and its own offset.
        $prepared = ['headers' => ['User-Agent' => 'acli/test', 'X-Cloud-IDE-UUID' => 'ide-1'], 'query' => []];
        $this->clientProphecy->modifyOptions()->willReturn($prepared);
        $offset = 0;
        foreach ($pages as $ids) {
            $items = array_map(static fn (string $id): array => ['id' => $id, 'label' => "Site $id"], $ids);
            $body = json_encode([
                'count' => count($ids),
                // No pagination.total: the command must read the top-level
                // total, which has the same value.
                'pagination' => ['limit' => $limit, 'offset' => $offset],
                'total' => $total,
                '_embedded' => ['items' => $items],
            ], JSON_THROW_ON_ERROR);
            $this->clientProphecy->makeRequest('get', '/source-sites', ['headers' => $prepared['headers'], 'query' => ['limit' => 100, 'offset' => $offset]])
                ->willReturn(new Response(200, ['Content-Type' => 'application/hal+json'], $body))
                ->shouldBeCalledOnce();
            $offset += count($ids);
        }
        // The real processResponse() unwraps _embedded.items; the prophecy must too.
        $this->clientProphecy->processResponse(Argument::type(ResponseInterface::class))
            ->will(static fn (array $args): array => json_decode((string) $args[0]->getBody(), flags: JSON_THROW_ON_ERROR)->_embedded->items);
    }

    public function testChooserRecordsChosenSite(): void
    {
        $this->mockSitePages([['site-a', 'site-b']]);
        $this->executeCommand([], [1]);
        $this->assertStringContainsString('Select a Source site', $this->getDisplay());
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-b\n");
    }

    public function testChooserOffersEveryPage(): void
    {
        $this->mockSitePages([['site-a', 'site-b'], ['site-c', 'site-d'], ['site-e']]);
        // The last site, on the third page.
        $this->executeCommand([], [4]);
        $display = $this->getDisplay();
        foreach (['site-a', 'site-b', 'site-c', 'site-d', 'site-e'] as $id) {
            $this->assertStringContainsString("Site $id", $display);
        }
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-e\n");
    }

    public function testEmptyPageEndsPagingDespiteTotal(): void
    {
        // The service claims five sites but has one page of one and then nothing.
        $this->mockSitePages([['site-a'], []], 5);
        $this->executeCommand([], [0]);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
    }

    public function testNoSitesThrows(): void
    {
        $this->mockSitePages([[]]);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('There are no Source sites to link to.');
        $this->executeCommand();
    }

    public function testWritesAtWorkingCopyRootFromSubdirectory(): void
    {
        mkdir($this->projectDir . '/.acquia/config/language/nl', 0777, true);
        $this->setCwd($this->command, $this->projectDir . '/.acquia/config/language/nl');
        $this->mockSite('site-a');
        $this->executeCommand(['sourceSiteId' => 'site-a']);
        $this->assertStringEqualsFile($this->projectDir . '/.acquia-cli.yml', "source_site_id: site-a\n");
    }

    public function testWritesInCwdWhenNothingPulledYet(): void
    {
        mkdir($this->projectDir . '/fresh');
        $this->setCwd($this->command, $this->projectDir . '/fresh');
        $this->mockSite('site-a');
        $this->executeCommand(['sourceSiteId' => 'site-a']);
        $this->assertStringEqualsFile($this->projectDir . '/fresh/.acquia-cli.yml', "source_site_id: site-a\n");
    }
}
