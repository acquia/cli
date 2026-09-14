<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'source:link', description: 'Associate your working copy with a Source site')]
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
        if ($siteId = $input->getArgument('sourceSiteId')) {
            // The ID becomes a URL path segment.
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $siteId)) {
                throw new AcquiaCliException('"{id}" is not a valid Source site ID: only letters, digits and hyphens are allowed.', ['id' => $siteId]);
            }
            $site = $client->request('get', "/source-sites/$siteId");
        } else {
            if ($linked = $datastore->get('source_site_id')) {
                $output->writeln("This working copy is already linked to Source site <options=bold>$linked</>. Run <options=bold>acli source:link <sourceSiteId></> to link it to another site.");
                return 1;
            }
            if (!$input->isInteractive()) {
                throw new AcquiaCliException('Pass the Source site ID as the sourceSiteId argument when running non-interactively.');
            }
            $sites = $client->request('get', '/source-sites');
            if (!$sites) {
                throw new AcquiaCliException('There are no Source sites to link to.');
            }
            // ponytail: first page of /source-sites only; add pagination when a subscription exceeds one page.
            $site = $this->promptChooseFromObjectsOrArrays($sites, 'id', 'label', 'Select a Source site');
        }
        $datastore->set('source_site_id', $site->id);
        $this->io->success("Linked this working copy to Source site $site->label ($site->id) by writing to $datastore->filepath");

        return Command::SUCCESS;
    }
}
