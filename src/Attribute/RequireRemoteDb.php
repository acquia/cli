<?php

declare(strict_types=1);

namespace Acquia\Cli\Attribute;

/**
 * Specify that a command requires remote database.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RequireRemoteDb
{
}
