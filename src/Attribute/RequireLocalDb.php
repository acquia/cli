<?php

declare(strict_types=1);

namespace Acquia\Cli\Attribute;

/**
 * Specify that a command requires local database.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RequireLocalDb
{
}
