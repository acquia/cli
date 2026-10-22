<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Hidden pre-rename name of source:cms:unlink, kept for backwards compatibility.
 */
#[AsCommand(name: 'source:unlink', description: "Remove the working copy's association with its Source site", hidden: true)]
final class LegacyUnlinkCommand extends UnlinkCommand
{
}
