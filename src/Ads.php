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
        $api_commands = Yaml::parseFile(__DIR__ . '/../assets/acquia-spec.yaml');
        foreach ($api_commands as $name => $command_definition) {
            $command = new ApiCommand($name);
            $command->setDescription($command_definition['description']);
            $input_definition = [];
            if (array_key_exists('arguments', $command_definition)) {
                foreach ($command_definition['arguments'] as $label => $argument) {
                    $input_definition[] = new InputArgument($label, InputArgument::REQUIRED, $argument['description']);
                }
            }
            if (array_key_exists('options', $command_definition)) {
                foreach ($command_definition['options'] as $label => $option) {
                    $input_definition[] = new InputOption($label, null, InputOption::VALUE_OPTIONAL,
                      $option['description']);
                }
            }
            if ($input_definition) {
                $command->setDefinition(new InputDefinition($input_definition));
            }
            $this->add($command);
        }
    }

}
