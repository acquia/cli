<?php

declare(strict_types=1);

namespace Acquia\Cli\DataStore;

use Symfony\Component\Config\Definition\ConfigurationInterface;

class JsonDataStore extends Datastore
{
    /**
     * Creates a new store.
     *
     * @param \Symfony\Component\Config\Definition\ConfigurationInterface|null $configDefinition
     */
    public function __construct(string $path, ConfigurationInterface $configDefinition = null)
    {
        parent::__construct($path);
        if ($this->fileSystem->exists($path)) {
            $array = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $array = $this->expander->expandArrayProperties($array);
            $cleaned = $this->cleanLegacyConfig($array);

            if ($configDefinition) {
                $array = $this->processConfig($array, $configDefinition, $path);
            }
            $this->data->import($array);

            // Dump the new values to disk.
            if ($cleaned) {
                $this->dump();
            }
        }
    }

    public function dump(): void
    {
        $this->fileSystem->dumpFile($this->filepath, json_encode($this->data->export(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    protected function cleanLegacyConfig(array &$array): bool
    {
        // Legacy format of credential storage.
        $dump = false;
        if (array_key_exists('key', $array) || array_key_exists('secret', $array)) {
            unset($array['key'], $array['secret']);
            $dump = true;
        }
        return $dump;
    }
}
