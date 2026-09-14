<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Helpers\SourceConfigDocument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'source:cms:config:pull', description: "Export a Source site's configuration to .acquia/config")]
final class ConfigPullCommand extends ConfigCommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->workingCopyDir();
        $siteId = $this->determineSourceSite($root);
        $response = $this->cloudApiClientService->getClient()->request('get', "/source-sites/$siteId/config");
        $files = SourceConfigDocument::toFiles($response->configuration);

        // Write everything into a sibling directory first, so that a failure
        // halfway leaves the existing .acquia/config untouched.
        $configDir = "$root/.acquia/config";
        $tmpDir = "$configDir.tmp";
        $filesystem = $this->localMachineHelper->getFilesystem();
        $filesystem->remove($tmpDir);
        try {
            foreach ($files as $path => $contents) {
                $filesystem->dumpFile("$tmpDir/$path", $contents);
            }
            $filesystem->remove($configDir);
            $filesystem->rename($tmpDir, $configDir);
        } finally {
            $filesystem->remove($tmpDir);
        }
        $this->io->success(sprintf('Exported %d configuration files from Source site %s to %s', count($files), $siteId, $configDir));

        return Command::SUCCESS;
    }
}
