<?php

declare(strict_types=1);

namespace Acquia\Cli\Command;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\SshKeys;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class WizardCommandBase extends SshKeyCommandBase
{
    abstract protected function validateEnvironment(): void;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ((new \ReflectionClass(static::class))->getAttributes(RequireAuth::class) && !$this->cloudApiClientService->isMachineAuthenticated()) {
            $commandName = 'auth:login';
            $command = $this->getApplication()->find($commandName);
            $arguments = ['command' => $commandName];
            $createInput = new ArrayInput($arguments);
            $exitCode = $command->run($createInput, $output);
            if ($exitCode !== 0) {
                throw new AcquiaCliException("Unable to authenticate with the Cloud Platform.");
            }
        }
        $this->validateEnvironment();

        parent::initialize($input, $output);
    }

    protected function deleteLocalSshKey(): void
    {
        $this->localMachineHelper->getFilesystem()->remove([
        $this->publicSshKeyFilepath,
        $this->privateSshKeyFilepath,
        ]);
    }

    protected function savePassPhraseToFile(string $passphrase): bool|int
    {
        return file_put_contents($this->passphraseFilepath, $passphrase);
    }

    protected function getPassPhraseFromFile(): string
    {
        return file_get_contents($this->passphraseFilepath);
    }

    /**
     * Assert whether ANY local key exists that has a corresponding key on the
     * Cloud Platform.
     */
    protected function userHasUploadedThisKeyToCloud(string $label): bool
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $sshKeys = new SshKeys($acquiaCloudClient);
        $cloudKeys = $sshKeys->getAll();
        /** @var \AcquiaCloudApi\Response\SshKeyResponse $cloudKey */
        foreach ($cloudKeys as $index => $cloudKey) {
            if (
                $cloudKey->label === $label
                // Assert that a corresponding local key exists.
                && $this->localSshKeyExists()
                // Assert local public key contents match Cloud public key contents.
                && $this->normalizePublicSshKey($cloudKey->public_key) === $this->normalizePublicSshKey(file_get_contents($this->publicSshKeyFilepath))
            ) {
                return true;
            }
        }
        return false;
    }

    protected function passPhraseFileExists(): bool
    {
        return file_exists($this->passphraseFilepath);
    }

    protected function localSshKeyExists(): bool
    {
        return file_exists($this->publicSshKeyFilepath) && file_exists($this->privateSshKeyFilepath);
    }
}
