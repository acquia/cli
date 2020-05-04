<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Command\CommandBase;

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
  protected function findLocalSshKeys(): array {
    $finder = new Finder();
    $finder->files()->in($this->getSshKeysDir())->name('*.pub');
    return iterator_to_array($finder);
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

  /**
   * @param $label
   *
   * @return string|string[]|null
   */
  public static function normalizeSshKeyLabel($label) {
    // It may only contain letters, numbers and underscores,.
    $label = preg_replace('/[^A-Za-z0-9_]/', '', $label);

    return $label;
  }

}
