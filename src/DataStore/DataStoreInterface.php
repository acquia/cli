<?php

declare(strict_types=1);

namespace Acquia\Cli\DataStore;

interface DataStoreInterface
{
    public function set(string $key, mixed $value): void;

    public function get(string $key): mixed;

    public function dump(): void;

    public function remove(string $key): void;

    public function exists(string $key): bool;
}
