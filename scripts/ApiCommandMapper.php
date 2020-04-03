<?php

namespace Acquia\Ads\Scripts;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ApiCommandMapper {

    public static function generateCommandMap(): void
    {
        $acquia_cloud_spec = Yaml::parseFile(__DIR__ . '/../assets/acquia-spec.yaml');
        $api_commands = [];
        foreach ($acquia_cloud_spec['paths'] as $path => $endpoint) {
            $args = [];
            $found = preg_match_all('#{([^}]+)}#', $path, $matches);
            if ($matches) {
                $args = $matches[1];
            }

            foreach ($endpoint as $method => $schema) {
                $command['method'] = $method;
                $command['description'] = $schema['summary'];
                if (array_key_exists('parameters', $schema)) {
                    foreach ($schema['parameters'] as $parameter) {
                        $parts = explode('/', $parameter['$ref']);
                        $param_name = end($parts);
                        $param_definition = $acquia_cloud_spec['components']['parameters'][$param_name];
                        $param = ['description' => $acquia_cloud_spec['components']['parameters'][$param_name]['description']];
                        $required = array_key_exists('required', $param_definition) && $param_definition['required'];
                        if ($required) {
                            $param['required'] = true;
                            $command['arguments'][$param_name] = $param;
                        }
                        else {
                            $param['required'] = false;
                            $command['options'][$param_name] = $param;
                        }
                    }
                }

                $command_name = 'api:' . $schema['x-cli-name'];
                $api_commands[$command_name] = $command;
            }
        }

        $fs = new Filesystem();
        $fs->dumpFile(__DIR__ . '/../assets/api_command_map.yml', Yaml::dump($api_commands, 3));
    }

}
