<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Command\CommandBase;

use Symfony\Component\Finder\Finder;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   */
  protected function findLocalSshKeys() {
    $finder = new Finder();
    $finder->files()->in($this->getApplication()->getLocalMachineHelper()->getHomeDir() . '/.ssh')->name('*.pub');
    $local_keys = iterator_to_array($finder);

    return $local_keys;
  }

}
