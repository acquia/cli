<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class AliasCache extends FilesystemAdapter
{
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        // Aliases format is `realm:name.env`, but `:` is not a legal character.
        $key = str_replace(':', '.', $key);
        return parent::get($key, $callback, $beta, $metadata);
    }
}
