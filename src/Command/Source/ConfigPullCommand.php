<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\SasApi\SourceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Trigger a Source config export on a site via the Sites Aggregation Service.
 *
 * Asks SAS to run a config export on the environment, which exports config
 * from the CMS. The exported config comes back as a single YAML document keyed
 * by collection, then config name; this command writes it out to
 * .acquia/config/ as individual files, replacing whatever is there.
 */
#[RequireAuth]
#[AsCommand(name: 'source:config:pull', description: 'Export Source configuration from a site')]
final class ConfigPullCommand extends ConfigCommandBase
{
    /**
     * The directory (relative to the project root) config is written to.
     */
    private const CONFIG_DIR = '.acquia/config';

    protected function triggerOperation(SourceConfig $sourceConfig, string $environmentId): object
    {
        return $sourceConfig->pull($environmentId);
    }

    protected function operationLabel(): string
    {
        return 'Exporting configuration';
    }

    /**
     * Fetch the exported payload and write it to .acquia/config/.
     *
     * The directory is wiped and rewritten so the local files mirror the
     * remote state exactly — config removed in the CMS disappears locally too.
     */
    protected function onSuccess(SourceConfig $sourceConfig, string $operationId): int
    {
        $yaml = $sourceConfig->getExportPayload($operationId);
        $payload = Yaml::parse($yaml);

        if (!is_array($payload)) {
            throw new AcquiaCliException('The SAS API returned an invalid config payload.');
        }

        $this->writePayload($payload);

        $this->io->success(sprintf('Configuration exported to %s.', self::CONFIG_DIR));

        return Command::SUCCESS;
    }

    /**
     * Wipe and rewrite .acquia/config/ from the payload.
     *
     * The payload maps collection names to config items. The default
     * collection ("") writes to the config root; other collections write to
     * dotted subdirectories (language.es becomes language/es).
     *
     * @param array<string, array<string, mixed>> $payload
     */
    private function writePayload(array $payload): void
    {
        $configDir = $this->dir . '/' . self::CONFIG_DIR;
        $filesystem = new Filesystem();

        // Wipe the directory so the local files mirror the remote state.
        $filesystem->remove($configDir);
        $filesystem->mkdir($configDir);

        foreach ($payload as $collection => $items) {
            if (!is_array($items)) {
                continue;
            }
            // The default collection ("") is the config root; other collections
            // map their dotted name to a subdirectory (language.es -> language/es).
            $collectionDir = $collection === ''
                ? $configDir
                : $configDir . '/' . str_replace('.', '/', $collection);

            foreach ($items as $name => $values) {
                $filesystem->dumpFile(
                    sprintf('%s/%s.yml', $collectionDir, $name),
                    Yaml::dump($values, 10, 2),
                );
            }
        }
    }
}
