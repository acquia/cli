<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base class for the commands that move configuration between a Source site
 * and the working copy's .acquia/config directory.
 */
abstract class ConfigCommandBase extends SourceCommandBase
{
    protected function configure(): void
    {
        $this
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'The Source site ID (defaults to the one recorded by acli source:link)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt or json)', 'txt');
    }

    /**
     * Whether --format=json was passed: the outcome is then the only stdout.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function outputsJson(): bool
    {
        $format = $this->input->getOption('format');
        if (!in_array($format, ['txt', 'json'], true)) {
            throw new AcquiaCliException('Unknown output format "{format}". Use txt or json.', ['format' => $format]);
        }
        return $format === 'json';
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineSourceSite(string $workingCopyDir): string
    {
        $siteId = $this->input->getOption('site') ?? $this->sourceDatastore($workingCopyDir)->get('source_site_id');
        if (!$siteId) {
            throw new AcquiaCliException('Could not determine the Source site. Pass --site=<sourceSiteId> or run acli source:link first.');
        }
        return $siteId;
    }
}
