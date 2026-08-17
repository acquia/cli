<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\SasApi\SourceConfig;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Trigger a Source config export on a site via the Sites Aggregation Service.
 *
 * Asks SAS to run a config export on the environment, which exports config
 * from the CMS and writes it to the site's git repository.
 */
#[RequireAuth]
#[AsCommand(name: 'source:config:pull', description: 'Export Source configuration from a site')]
final class ConfigPullCommand extends ConfigCommandBase
{
    protected function triggerOperation(SourceConfig $sourceConfig, string $environmentId): object
    {
        return $sourceConfig->pull($environmentId);
    }

    protected function operationLabel(): string
    {
        return 'Exporting configuration';
    }
}
