<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\SasApi\SourceConfig;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Trigger a Source config import on a site via the Sites Aggregation Service.
 *
 * Asks SAS to run `drush source:config:import` on the environment, which
 * reads config from the site's git repository and applies it to the CMS.
 */
#[RequireAuth]
#[AsCommand(name: 'source:config:push', description: 'Import deployed Source configuration on a site')]
final class ConfigPushCommand extends ConfigCommandBase
{
    protected function triggerOperation(SourceConfig $sourceConfig, string $environmentId): object
    {
        return $sourceConfig->push($environmentId);
    }

    protected function operationLabel(): string
    {
        return 'Importing configuration';
    }
}
