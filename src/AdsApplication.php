<?php

namespace Acquia\Ads;

use Acquia\Ads\Command\Api\ApiCommandHelper;
use Acquia\Ads\DataStore\FileStore;
use Acquia\Ads\Helpers\LocalMachineHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandBase
 *
 * @package Grasmash\YamlCli\Command
 */
class AdsApplication extends Application implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    /** @var \Acquia\Ads\DataStore\FileStore */
    private $datastore;

    /** @var null|string */
    private $repoRoot;

    /**
     * @var \Acquia\Ads\Helpers\LocalMachineHelper
     */
    protected $localMachineHelper;

    /**
     * @return \Acquia\Ads\Helpers\LocalMachineHelper
     */
    public function getLocalMachineHelper(): LocalMachineHelper
    {
        return $this->localMachineHelper;
    }

    /**
     * Ads constructor.
     *
     * @param string $name
     * @param string $version
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Psr\Log\LoggerInterface $logger
     * @param $repo_root
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function __construct(
        string $name = 'UNKNOWN',
        string $version = 'UNKNOWN',
        InputInterface $input,
        OutputInterface $output,
        LoggerInterface $logger,
        $repo_root
    ) {
        $this->setLogger($logger);
        $this->warnIfXdebugLoaded();
        $this->repoRoot = $repo_root;
        $this->localMachineHelper = new LocalMachineHelper($input, $output, $logger);
        parent::__construct($name, $version);
        $this->datastore = new FileStore($this->getLocalMachineHelper()->getHomeDir() . '/.acquia');

        // Add API commands.
        $api_command_helper = new ApiCommandHelper();
        $this->addCommands($api_command_helper->getApiCommands());

        // Register custom progress bar format.
        ProgressBar::setFormatDefinition(
            'message',
            "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%"
        );
    }

    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     *
     * @throws \Exception When running fails. Bypass this when {@link setCatchExceptions()}.
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        // @todo Add telemetry.

        $exit_code = parent::run($input, $output);
        return $exit_code;
    }

    /**
     * Initializes Amplitude.
     */
    private function initializeAmplitude()
    {
        $userConfig = new UserConfig(self::configDir());
        $amplitude = Amplitude::getInstance();
        $amplitude->init('dfd3cba7fa72065cde9edc2ca22d0f37')
          ->setDeviceId(EnvironmentDetector::getMachineUuid());
        if (!$userConfig->isTelemetryEnabled()) {
            $amplitude->setOptOut(true);
        }
        $amplitude->logQueuedEvents();
    }

    /**
     * @return null|string
     */
    public function getRepoRoot(): ?string
    {
        return $this->repoRoot;
    }

    /**
     * Warns the user if the xDebug extension is loaded.
     */
    protected function warnIfXdebugLoaded()
    {
        $xdebug_loaded = extension_loaded('xdebug');
        if ($xdebug_loaded) {
            $this->logger->warning('<comment>The xDebug extension is loaded. This will significantly decrease performance.</comment>');
        }
    }

    public function getDataStore()
    {
        return $this->datastore;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
