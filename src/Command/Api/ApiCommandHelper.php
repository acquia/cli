<?php

namespace Acquia\Ads\Command\Api;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class ApiCommandHelper
{

    /**
     * @return ApiCommandBase[]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getApiCommands(): array
    {
        // The acquia-spec.yaml is copied directly from the acquia/cx-api-spec repository. It can be updated
        // by running `composer update-cloud-api-spec`.
        $acquia_cloud_spec_file = __DIR__ . '/../../../assets/acquia-spec.yaml';
        $acquia_cloud_spec_file_checksum = md5_file($acquia_cloud_spec_file);

        $cache = new PhpArrayAdapter(
            __DIR__ . '/../../../cache/ApiCommands.cache',
            new FilesystemAdapter()
        );

        // Check to see if the API spec has changed since we cached commands.
        $is_command_cache_valid = $this->isCommandCacheValid($cache, $acquia_cloud_spec_file_checksum);
        $api_commands_cache_item = $cache->getItem('commands.api');
        if ($is_command_cache_valid && $api_commands_cache_item->isHit()) {
            return $api_commands_cache_item->get();
        }

        $acquia_cloud_spec = Yaml::parseFile($acquia_cloud_spec_file);
        $api_commands = [];
        foreach ($acquia_cloud_spec['paths'] as $path => $endpoint) {
            foreach ($endpoint as $method => $schema) {
                $command_name = 'api:' . $schema['x-cli-name'];
                $command = new ApiCommandBase($command_name);
                $command->setDescription($schema['summary']);
                $command->setMethod($method);
                $command->setResponses($schema['responses']);
                $command->setServers($acquia_cloud_spec['servers']);
                $command->setPath($path);
                // This is unhidden when `ads api:list` is run.
                $command->setHidden(true);
                $this->addApiCommandParameters($schema, $acquia_cloud_spec, $command);
                $api_commands[] = $command;
            }
        }

        // Save the API spec file checksum and api commands to the cache.
        $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
        $api_spec_checksum_item->set($acquia_cloud_spec_file_checksum);
        $cache->save($api_spec_checksum_item);
        $api_commands_cache_item->set($api_commands);
        $cache->save($api_commands_cache_item);

        return $api_commands;
    }

    /**
     * @param $param_definition
     * @param string $usage
     *
     * @return mixed|string
     */
    protected function addArgumentExampleToUsage($param_definition, string $usage)
    {
        if (array_key_exists('example', $param_definition)) {
            if (is_array($param_definition['example'])) {
                $usage = reset($param_definition['example']);
            } else if (strpos($param_definition['example'], ' ') !== false) {
                $usage .= '"' . $param_definition['example'] . '" ';
            } else {
                $usage .= $param_definition['example'] . ' ';
            }
        }

        return $usage;
    }

    /**
     * @param $param_definition
     * @param $param_name
     * @param string $usage
     *
     * @return string
     */
    protected function addOptionExampleToUsage($param_definition, $param_name, string $usage): string
    {
        if (array_key_exists('example', $param_definition)) {
            $usage .= '--' . $param_name . '="' . $param_definition['example'] . '""';
        }

        return $usage;
    }

    /**
     * @param $schema
     * @param $acquia_cloud_spec
     * @param \Acquia\Ads\Command\Api\ApiCommandBase $command
     */
    protected function addApiCommandParameters($schema, $acquia_cloud_spec, ApiCommandBase $command): void
    {
        if (array_key_exists('parameters', $schema)) {
            $usage = '';
            $input_definition = [];
            foreach ($schema['parameters'] as $parameter) {
                $parts = explode('/', $parameter['$ref']);
                $param_name = end($parts);
                $param_definition = $acquia_cloud_spec['components']['parameters'][$param_name];
                $required = array_key_exists('required', $param_definition) && $param_definition['required'];
                if ($required) {
                    $input_definition[] = new InputArgument(
                        $param_definition['name'],
                        InputArgument::REQUIRED,
                        $acquia_cloud_spec['components']['parameters'][$param_name]['description']
                    );
                    $usage = $this->addArgumentExampleToUsage($param_definition, $usage);
                } else {
                    $input_definition[] = new InputOption(
                        $param_definition['name'],
                        null,
                        InputOption::VALUE_OPTIONAL,
                        $acquia_cloud_spec['components']['parameters'][$param_name]['description']
                    );
                    $usage = $this->addOptionExampleToUsage($param_definition, $param_name, $usage);
                }
            }
            if ($input_definition) {
                $command->setDefinition(new InputDefinition($input_definition));
            }

            $command->addUsage($usage);
        }
    }

    /**
     * @param \Symfony\Component\Cache\Adapter\PhpArrayAdapter $cache
     *
     * @param string $acquia_cloud_spec_file_checksum
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function isCommandCacheValid(PhpArrayAdapter $cache, $acquia_cloud_spec_file_checksum): bool
    {
        $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
        // If there's an invalid entry OR there's no entry, return false.
        if (!$api_spec_checksum_item->isHit() || ($api_spec_checksum_item->isHit() && $api_spec_checksum_item->get() !== $acquia_cloud_spec_file_checksum)) {
            return false;
        }

        return true;
    }
}
