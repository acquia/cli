<?php

namespace Acquia\Ads;

use Acquia\Ads\Command\Api\ApiCommandHelper;
use Acquia\Ads\DataStore\FileStore;
use Symfony\Component\Console\Application;

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
        $api_command_helper = new ApiCommandHelper();
        $this->addCommands($api_command_helper->getApiCommands());
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
