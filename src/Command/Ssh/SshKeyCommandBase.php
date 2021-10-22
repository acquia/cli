<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Response\IdeResponse;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   * @throws \Exception
   */
  protected function findLocalSshKeys(): array {
    $finder = $this->localMachineHelper->getFinder();
    $finder->files()->in($this->sshDir)->name('*.pub')->ignoreUnreadableDirs();
    return iterator_to_array($finder);
  }

  /**
   *
   * @param \AcquiaCloudApi\Response\IdeResponse $ide
   *
   * @return string
   */
  public static function getIdeSshKeyLabel(IdeResponse $ide): string {
    return self::normalizeSshKeyLabel('IDE_' . $ide->label . '_' . $ide->uuid);
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
