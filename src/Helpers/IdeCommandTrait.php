<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Safe\Exceptions\FilesystemException;

trait IdeCommandTrait
{
    private string $phpVersionFilePath;

    private function getIdePhpVersion(): ?string
    {
        try {
            return trim($this->localMachineHelper->readFile($this->getIdePhpVersionFilePath()));
        } catch (FilesystemException) {
            return null;
        }
    }

    public function setPhpVersionFilePath(string $path): void
    {
        $this->phpVersionFilePath = $path;
    }

    protected function getIdePhpVersionFilePath(): string
    {
        if (!isset($this->phpVersionFilePath)) {
            $this->phpVersionFilePath = '/home/ide/configs/php/.version';
        }
        return $this->phpVersionFilePath;
    }
}
