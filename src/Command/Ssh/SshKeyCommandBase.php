<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;

use Symfony\Component\Finder\Finder;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

  /** @var string|null */
  private $sshKeysDir = NULL;

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   */
  protected function findLocalSshKeys() {
    $finder = new Finder();
    $finder->files()->in($this->getSshKeysDir())->name('*.pub')->ignoreUnreadableDirs();
    $local_keys = iterator_to_array($finder);

    return $local_keys;
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
  protected function getSshKeysDir(): string {
    if (!isset($this->sshKeysDir)) {
    $this->sshKeysDir = $this->getApplication()->getLocalMachineHelper()->getLocalFilepath('~/.ssh');
    }

    return $this->sshKeysDir;
  }

}
