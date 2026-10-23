<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Config\AcquiaCliConfig;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;

/**
 * Base class for commands that operate on a Source working copy.
 *
 * A working copy is the directory holding .acquia/config, or before the first
 * pull the one whose .acquia-cli.yml has a source site ID; it has no docroot,
 * so the project directory acli infers for Drupal projects does not apply.
 * The commands may run from any directory inside the working copy, including
 * from inside .acquia/config itself.
 */
abstract class SourceCommandBase extends CommandBase
{
    /**
     * The directory the search for the working copy starts from.
     *
     * Defaults to the current directory; tests set it by reflection so that
     * they never have to chdir().
     */
    protected string $cwd;

    protected function workingCopyDir(): string
    {
        return LocalMachineHelper::getSourceWorkingCopyDir($this->cwd ?? getcwd());
    }

    /**
     * Rejects an ID the API would not accept, before it becomes a URL path segment.
     *
     * The pattern is a valid source site ID, per the Source Platform API spec.
     * The server validates too, but that surfaces as a confusing 404 so it's
     * better to validate the site ID before contacting the server.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected static function assertValidSiteId(string $siteId): void
    {
        // D: without it, $ also matches before a trailing newline.
        if (!preg_match('/^[a-zA-Z0-9-]+$/D', $siteId)) {
            throw new AcquiaCliException('"{id}" is not a valid Source site ID: only letters, digits and hyphens are allowed.', ['id' => $siteId]);
        }
    }

    /**
     * The .acquia-cli.yml at the working copy root, where the Source site is recorded.
     */
    protected function sourceDatastore(string $workingCopyDir): AcquiaCliDatastore
    {
        return new AcquiaCliDatastore($this->localMachineHelper, new AcquiaCliConfig(), $workingCopyDir . '/.acquia-cli.yml');
    }
}
