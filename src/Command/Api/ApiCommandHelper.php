<?php

namespace Acquia\Ads\Command\Api;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class ApiCommandHelper {

    /**
     * @return ApiCommandBase[]
     */
    public function getApiCommands(): array
    {
        // The acquia-spec.yaml is copied directly from the acquia/cx-api-spec repository. It can be updated
        // by running `composer update-cloud-api-spec`.
        // @todo Figure out how to improve the performance of this parse operation when xdebug is enabled.
        $acquia_cloud_spec = Yaml::parseFile(__DIR__ . '/../../../assets/acquia-spec.yaml');
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
                // @todo Make this hidden unless someone is running the `api:list` command.
                $command->setHidden(true);
                $this->addApiCommandParameters($schema, $acquia_cloud_spec, $command);
                $api_commands[] = $command;
            }
        }

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
     * @param \Acquia\Ads\Command\ApiCommand $command
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
                    $input_definition[] = new InputArgument($param_definition['name'], InputArgument::REQUIRED,
                      $acquia_cloud_spec['components']['parameters'][$param_name]['description']);
                    $usage = $this->addArgumentExampleToUsage($param_definition, $usage);
                } else {
                    $input_definition[] = new InputOption($param_definition['name'], null, InputOption::VALUE_OPTIONAL,
                      $acquia_cloud_spec['components']['parameters'][$param_name]['description']);
                    $usage = $this->addOptionExampleToUsage($param_definition, $param_name, $usage);
                }
            }
            if ($input_definition) {
                $command->setDefinition(new InputDefinition($input_definition));
            }

            $command->addUsage($usage);
        }
    }
}
