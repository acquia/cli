<?php

namespace Acquia\Ads;

use Acquia\Ads\Command\ApiCommand;
use Acquia\Ads\DataStore\FileStore;
use Acquia\Ads\Session\Session;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Application;
use Dflydev\DotAccessData\Data;
use Grasmash\YamlCli\Loader\JsonFileLoader;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CommandBase
 *
 * @package Grasmash\YamlCli\Command
 */
class Ads extends Application {

    /** @var \Acquia\Ads\DataStore\FileStore  */
    private $datastore;

    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);
        $this->datastore = new FileStore($this->getHomeDir() . '/.acquia');
        $this->addApiCommands();
    }

    public function getDataStore()
    {
        return $this->datastore;
    }

    /**
     * Returns the appropriate home directory.
     *
     * Adapted from Ads Package Manager by Ed Reel
     * @author Ed Reel <@uberhacker>
     * @url    https://github.com/uberhacker/tpm
     *
     * @return string
     */
    protected function getHomeDir()
    {
        $home = getenv('HOME');
        if (!$home) {
            $system = '';
            if (getenv('MSYSTEM') !== null) {
                $system = strtoupper(substr(getenv('MSYSTEM'), 0, 4));
            }
            if ($system != 'MING') {
                $home = getenv('HOMEPATH');
            }
        }
        return $home;
    }

    protected function addApiCommands(): void
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
                $command = new ApiCommand('api:' . $schema['x-cli-name']);
                $command->setDescription($schema['summary']);
                if (array_key_exists('parameters', $schema)) {
                    $input_definition = [];
                    foreach ($schema['parameters'] as $parameter) {
                        $parts = explode('/', $parameter['$ref']);
                        $param_name = end($parts);
                        $param = [
                          'required' => $acquia_cloud_spec['components']['parameters'][$param_name]['required'],
                          'description' => $acquia_cloud_spec['components']['parameters'][$param_name]['description'],
                        ];
                        $required = array_key_exists('required', $param) && $param['required'];
                        if ($required){
                            $command['arguments'] = $param;
                        }
                        else {
                            $command['options'] = $param;
                        }
                    }
                    $command->setDefinition(new InputDefinition($input_definition));
                }
                $api_commands[] = $command;
            }
        }
        $this->addCommands($api_commands);
    }

}
