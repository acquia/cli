<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'source:cms:link', description: 'Associate your working copy with a Source site')]
final class LinkCommand extends SourceCommandBase
{
    protected function configure(): void
    {
        $this->addArgument('sourceSiteId', InputArgument::OPTIONAL, 'The Source site ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $datastore = $this->sourceDatastore($this->workingCopyDir());
        $client = $this->cloudApiClientService->getClient();
        if (($siteId = $input->getArgument('sourceSiteId')) !== null) {
            // The ID becomes a URL path segment.
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $siteId)) {
                throw new AcquiaCliException('"{id}" is not a valid Source site ID: only letters, digits and hyphens are allowed.', ['id' => $siteId]);
            }
            $site = $client->request('get', "/source-sites/$siteId");
        } else {
            if ($linked = $datastore->get('source_site_id')) {
                $output->writeln("This working copy is already linked to Source site <options=bold>$linked</>. Run <options=bold>acli source:cms:link <sourceSiteId></> to link it to another site.");
                return 1;
            }
            if (!$input->isInteractive()) {
                throw new AcquiaCliException('Pass the Source site ID as the sourceSiteId argument when running non-interactively.');
            }
            $sites = $this->allSourceSites($client);
            if (!$sites) {
                throw new AcquiaCliException('There are no Source sites to link to.');
            }
            $site = $this->promptChooseFromObjectsOrArrays($sites, 'id', 'label', 'Select a Source site');
        }
        $datastore->set('source_site_id', $site->id);
        $this->io->success("Linked this working copy to Source site $site->label ($site->id) by writing to $datastore->filepath");

        return Command::SUCCESS;
    }

    /**
     * Every Source site the caller can see, across all pages.
     *
     * Client::request() unwraps _embedded.items and drops the pagination
     * envelope, so the raw response is read for `total` and the next page is
     * fetched by offset until it is reached.
     *
     * @return array<object>
     */
    private function allSourceSites(Client $client): array
    {
        $sites = [];
        do {
            // request() is bypassed (see above), so what it would have done
            // first is done here: modifyOptions() adds the headers acli
            // configured, such as User-Agent and X-Cloud-IDE-UUID.
            $options = $client->modifyOptions();
            // Big enough that nearly everyone needs only one request.
            $options['query']['limit'] = 100;
            $options['query']['offset'] = count($sites);
            $response = $client->makeRequest('get', '/source-sites', $options);
            // processResponse() throws for an error response and returns the items otherwise.
            $page = $client->processResponse($response);
            $sites = [...$sites, ...$page];
            $total = json_decode((string) $response->getBody(), flags: JSON_THROW_ON_ERROR)->total;
            // An empty page ends it even if the total claims otherwise.
        } while ($page && count($sites) < $total);
        return $sites;
    }
}
