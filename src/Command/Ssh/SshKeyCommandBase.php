<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;

use Symfony\Component\Finder\Finder;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   */
  protected function findLocalSshKeys(): array {
    $finder = $this->getApplication()->getLocalMachineHelper()->getFinder();
    $finder->files()->in($this->getApplication()->getSshKeysDir())->name('*.pub')->ignoreUnreadableDirs();
    return iterator_to_array($finder);
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
