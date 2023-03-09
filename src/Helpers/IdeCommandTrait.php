<?php

namespace Acquia\Cli\Helpers;

trait IdeCommandTrait {

  private string $phpVersionFilePath;

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  private function getIdePhpVersion(): string {
    return trim($this->localMachineHelper->readFile($this->getIdePhpVersionFilePath()));
  }

  /**
   */
  public function setPhpVersionFilePath(string $path): void {
    $this->phpVersionFilePath = $path;
  }

  /**
   */
  protected function getIdePhpVersionFilePath(): string {
    if (!isset($this->phpVersionFilePath)) {
      $this->phpVersionFilePath = '/home/ide/configs/php/.version';
    }
    return $this->phpVersionFilePath;
  }

}
