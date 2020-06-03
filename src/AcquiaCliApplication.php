<?php

namespace Acquia\Cli;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Helpers\SshHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;

/**
 * Class AcquiaCliApplication.
 */
class AcquiaCliApplication extends Application implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * @var \Symfony\Component\DependencyInjection\Container
   */
  private $container;

  /**
   * @var string|null
   */
  private $sshKeysDir;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  protected $sshHelper;

  /**
   * @return \Acquia\Cli\Helpers\SshHelper
   */
  public function getSshHelper(): SshHelper {
    return $this->sshHelper;
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

  /**
   * @return string
   * @throws \Exception
   */
  public function getSshKeysDir(): string {
    if (!isset($this->sshKeysDir)) {
      $this->sshKeysDir = $this->getContainer()->get('local_machine_helper')->getLocalFilepath('~/.ssh');
    }

    return $this->sshKeysDir;
  }

}
