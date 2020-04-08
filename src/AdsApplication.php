<?php

namespace Acquia\Ads;

use Acquia\Ads\Command\Api\ApiCommandHelper;
use Acquia\Ads\Command\ListCommand;
use Acquia\Ads\DataStore\FileStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandBase
 *
 * @package Grasmash\YamlCli\Command
 */
class AdsApplication extends Application implements LoggerAwareInterface {

    use LoggerAwareTrait;

    /** @var \Acquia\Ads\DataStore\FileStore  */
    private $datastore;

    /**
     * Ads constructor.
     *
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN', LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->warnIfXdebugLoaded();
        parent::__construct($name, $version);
        $this->datastore = new FileStore($this->getHomeDir() . '/.acquia');
        $api_command_helper = new ApiCommandHelper();
        $this->addCommands($api_command_helper->getApiCommands());
    }

    /**
     * Warns the user if the xDebug extension is loaded.
     */
    protected function warnIfXdebugLoaded() {
        $xdebug_loaded = extension_loaded('xdebug');
        if ($xdebug_loaded) {
            $this->logger->warning("<comment>The xDebug extension is loaded. This will significantly decrease performance.</comment>");
        }
    }

    /**
     * Gets the default commands that should always be available.
     *
     * In this, we provide a custom list command for ADS.
     *
     * @return Command[] An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        return [new HelpCommand(), new ListCommand()];
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

}
