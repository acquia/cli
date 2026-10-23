<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Helpers\SourceConfigDocument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;

#[RequireAuth]
#[AsCommand(name: 'source:cms:config:pull', description: "Export a Source site's configuration to .acquia/config")]
final class ConfigPullCommand extends ConfigCommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = $this->outputsJson();
        $root = $this->workingCopyDir();
        $siteId = $this->determineSourceSite($root);
        if (!$json) {
            $this->io->writeln("Exporting Source site $siteId's configuration...");
        }
        $response = $this->cloudApiClientService->getClient()->request('get', "/source-sites/$siteId/config");
        $files = SourceConfigDocument::toFiles($response->configuration);

        // Write everything into a sibling directory first, so that a failure
        // halfway leaves the existing .acquia/config untouched. Then swap by
        // renaming rather than removing: .acquia/config is what marks the
        // working copy, so it must not be absent for longer than two renames.
        $configDir = "$root/.acquia/config";
        $tmpDir = "$configDir.tmp";
        $oldDir = "$configDir.old";
        $filesystem = $this->localMachineHelper->getFilesystem();
        $filesystem->remove([$tmpDir, $oldDir]);
        try {
            foreach ($files as $path => $contents) {
                $filesystem->dumpFile("$tmpDir/$path", $contents);
            }
            if (is_dir($configDir)) {
                $filesystem->rename($configDir, $oldDir);
            }
            try {
                $filesystem->rename($tmpDir, $configDir);
            } catch (IOException $e) {
                if (is_dir($oldDir)) {
                    $filesystem->rename($oldDir, $configDir);
                }
                throw $e;
            }
        } finally {
            $filesystem->remove([$tmpDir, $oldDir]);
        }
        if ($json) {
            $output->writeln(json_encode(['directory' => $configDir, 'files' => array_keys($files)], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->io->success(sprintf('Exported %d configuration files from Source site %s to %s', count($files), $siteId, $configDir));
        }

        return Command::SUCCESS;
    }
}
