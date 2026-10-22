<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Source;

use Acquia\Cli\Attribute\RequireAuth;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Hidden pre-rename name of source:cms:link, kept for backwards compatibility.
 */
#[RequireAuth]
#[AsCommand(name: 'source:link', description: 'Associate your working copy with a Source site', hidden: true)]
final class LegacyLinkCommand extends LinkCommand
{
}
