<?php

namespace Acquia\Ads;

use Acquia\Ads\Command\Api\ApiCommandHelper;
use Acquia\Ads\DataStore\FileStore;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;

/**
 * Class CommandBase
 *
 * @package Grasmash\YamlCli\Command
 */
class AdsApplication extends Application implements LoggerAwareInterface {

    use LoggerAwareTrait;

    /** @var \Acquia\Ads\DataStore\FileStore  */
    private $datastore;

    /** @var string */
    private $repoRoot;

    /**
     * Ads constructor.
     *
     * @param string $name
     * @param string $version
     */
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN', LoggerInterface $logger, $repo_root)
    {
        $this->setLogger($logger);
        $this->warnIfXdebugLoaded();
        $this->repoRoot = $repo_root;
        parent::__construct($name, $version);
        $this->datastore = new FileStore($this->getHomeDir() . '/.acquia');
        $api_command_helper = new ApiCommandHelper();
        // @todo Skip if we're not running a list or api command.
        $this->addCommands($api_command_helper->getApiCommands());
    }

    /**
     * @return string
     */
    public function getRepoRoot(): string
    {
        return $this->repoRoot;
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
