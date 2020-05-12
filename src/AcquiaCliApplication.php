<?php

namespace Acquia\Cli;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Connector\CliCloudConnector;
use Acquia\Cli\Helpers\DataStoreAwareTrait;
use Acquia\Cli\Helpers\LocalMachineHelper;
use AcquiaCloudApi\Connector\Client;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\KeyValueStore\JsonFileStore;

/**
 * Class CommandBase.
 *
 * @package Grasmash\YamlCli\Command
 */
class AcquiaCliApplication extends Application implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use DataStoreAwareTrait;

  /**
   * @var null|string*/
  private $repoRoot;

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  protected $localMachineHelper;
  /**
   * @var string|null
   */
  private $sshKeysDir;
  /**
   * @var \AcquiaCloudApi\Connector\Client
   */
  private $acquiaCloudClient;

  /**
   * @var string
   */
  protected $acliConfigFilename = 'acquia-cli.json';

  /**
   * @var string
   */
  protected $cloudConfigFilename = 'cloud_api.conf';

  /**
   * @return \Acquia\Cli\Helpers\LocalMachineHelper
   */
  public function getLocalMachineHelper(): LocalMachineHelper {
    return $this->localMachineHelper;
  }

  /**
   * Cli constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $repo_root
   *
   * @param string $version
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function __construct(
        LoggerInterface $logger,
        InputInterface $input,
        OutputInterface $output,
        $repo_root,
        string $version = 'UNKNOWN'
    ) {
    $this->setLogger($logger);
    $this->warnIfXdebugLoaded();
    $this->repoRoot = $repo_root;
    $this->setLocalMachineHelper(new LocalMachineHelper($input, $output, $logger));
    parent::__construct('acli', $version);
    $this->setDatastore(new JsonFileStore($this->getLocalMachineHelper()->getHomeDir() . '/.acquia/storage.json'));

    // Add API commands.
    $api_command_helper = new ApiCommandHelper();
    $this->addCommands($api_command_helper->getApiCommands());

    // Register custom progress bar format.
    ProgressBar::setFormatDefinition(
          'message',
          "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%\n"
      );
  }

  /**
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   */
  public function setLocalMachineHelper(
    LocalMachineHelper $localMachineHelper
  ): void {
    $this->localMachineHelper = $localMachineHelper;
  }

  /**
   * Runs the current application.
   *
   * @param \Symfony\Component\Console\Input\InputInterface|null $input
   * @param \Symfony\Component\Console\Output\OutputInterface|null $output
   *
   * @return int 0 if everything went fine, or an error code
   *
   * @throws \Exception When running fails. Bypass this when <a href='psi_element://setCatchExceptions()'>setCatchExceptions()</a>.
   */
  public function run(InputInterface $input = NULL, OutputInterface $output = NULL) {
    // @todo Add telemetry.
    $exit_code = parent::run($input, $output);
    return $exit_code;
  }

  /**
   * @return null|string
   */
  public function getRepoRoot(): ?string {
    return $this->repoRoot;
  }

  /**
   * Warns the user if the xDebug extension is loaded.
   */
  protected function warnIfXdebugLoaded() {
    $xdebug_loaded = extension_loaded('xdebug');
    if ($xdebug_loaded) {
      $this->logger->warning('<comment>The xDebug extension is loaded. This will significantly decrease performance.</comment>');
    }
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

  /**
   * @param string|null $sshKeysDir
   */
  public function setSshKeysDir(?string $sshKeysDir): void {
    $this->sshKeysDir = $sshKeysDir;
  }

  /**
   * @return string
   */
  public function getSshKeysDir(): string {
    if (!isset($this->sshKeysDir)) {
      $this->sshKeysDir = $this->getLocalMachineHelper()->getLocalFilepath('~/.ssh');
    }

    return $this->sshKeysDir;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $client
   */
  public function setAcquiaCloudClient(Client $client) {
    $this->acquiaCloudClient = $client;
  }

  /**
   * @return \AcquiaCloudApi\Connector\Client
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function getAcquiaCloudClient(): Client {
    if (isset($this->acquiaCloudClient)) {
      return $this->acquiaCloudClient;
    }

    $cloud_api_conf = $this->datastore->get('cloud_api.conf');
    $config = [
      'key' => $cloud_api_conf['key'],
      'secret' => $cloud_api_conf['secret'],
    ];
    $connector = new CliCloudConnector($config);
    $this->acquiaCloudClient = Client::factory($connector);

    return $this->acquiaCloudClient;
  }

  /**
   * @return string
   */
  public function getCloudConfigFilename(): string {
    return $this->cloudConfigFilename;
  }

  /**
   * @return string
   */
  public function getAcliConfigFilename(): string {
    return $this->acliConfigFilename;
  }

}
