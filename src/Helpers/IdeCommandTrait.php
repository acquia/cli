<?php

namespace Acquia\Cli\Helpers;

trait IdeCommandTrait {

  /**
   * @var string
   */
  private string $phpVersionFilePath;

  /**
   * @return string
   */
  protected function getIdePhpVersion(): string {
    return trim($this->localMachineHelper->readFile($this->getIdePhpVersionFilePath()));
  }

  /**
   * @param string $path
   */
  public function setPhpVersionFilePath(string $path): void {
    $this->phpVersionFilePath = $path;
  }

  /**
   * @return string
   */
  public function getIdePhpVersionFilePath(): string {
    if (!isset($this->phpVersionFilePath)) {
      $this->phpVersionFilePath = '/home/ide/configs/php/.version';
    }
    return $this->phpVersionFilePath;
  }

}
