<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'source:unlink', description: "Remove the working copy's association with its Source site")]
final class UnlinkCommand extends SourceCommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $datastore = $this->sourceDatastore($this->workingCopyDir());
        $siteId = $datastore->get('source_site_id');
        if (!$siteId) {
            throw new AcquiaCliException('This working copy is not linked to a Source site.');
        }
        $datastore->remove('source_site_id');
        $this->io->success("Unlinked this working copy from Source site $siteId.");

        return Command::SUCCESS;
    }
}
