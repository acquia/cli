<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\SshKeys;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zumba\Amplitude\Amplitude;

trait SshCommandTrait
{
    private function deleteSshKeyFromCloud(mixed $output, mixed $cloudKey = null): int
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $sshKeys = new SshKeys($acquiaCloudClient);

        // If the user has provided a key, use it fetch key object. Otherwise, prompt to choose one.
        $cloudKey = !empty($cloudKey) && is_string($cloudKey) ? $sshKeys->get($cloudKey) : $this->determineCloudKey($acquiaCloudClient);

        $sshKeys->delete($cloudKey->uuid);
        $output->writeln("<info>Successfully deleted SSH key <options=bold>$cloudKey->label</> from the Cloud Platform.</info>");
        $localKeys = $this->findLocalSshKeys();
        foreach ($localKeys as $localFile) {
            $localFileContents = $localFile->getContents() ?? '';
            if (trim($localFileContents) === trim($cloudKey->public_key) && $localFile->getRealPath()) {
                $privateKeyPath = str_replace('.pub', '', $localFile->getRealPath());
                $publicKeyPath = $localFile->getRealPath();
                $answer = $this->io->confirm("Do you also want to delete the corresponding local key files {$localFile->getRealPath()} and $privateKeyPath ?", false);
                if ($answer) {
                    $this->localMachineHelper->getFilesystem()->remove([
                        $localFile->getRealPath(),
                        $privateKeyPath,
                    ]);
                    $this->io->success("Deleted $publicKeyPath and $privateKeyPath");
                    return 0;
                }
            }
        }
        return 0;
    }

    private function determineCloudKey(Client $acquiaCloudClient): object|array|null
    {
        $sshKeys = new SshKeys($acquiaCloudClient);
        $cloudKeys = $sshKeys->getAll();
        if (!$cloudKeys->count()) {
            throw new AcquiaCliException('There are no SSH keys associated with your account.');
        }
        return $this->promptChooseFromObjectsOrArrays(
            $cloudKeys,
            'uuid',
            'label',
            'Choose an SSH key to delete from the Cloud Platform'
        );
    }

    /**
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    protected function findLocalSshKeys(): array
    {
        $finder = $this->localMachineHelper->getFinder();
        $finder->files()
            ->in($this->sshDir)
            ->name('*.pub')
            ->ignoreUnreadableDirs();

        $validKeys = [];
        $pattern = '/^(ssh-(rsa|ed25519)|ecdsa-sha2-nistp(256|384|521)) [A-Za-z0-9+\/=]+(?: .*)?$/';
        foreach ($finder as $file) {
            $contents = trim($file->getContents() ?? '');
            if (preg_match($pattern, $contents)) {
                $validKeys[] = $file;
            }
        }
        return $validKeys;
    }

    protected function promptWaitForSsh(SymfonyStyle $io): bool
    {
        $io->note("It may take an hour or more before the SSH key is installed on all of your application's servers. Create a Support ticket for further assistance.");
        $wait = $io->confirm("Would you like to wait until your key is installed on all of your application's servers?");
        Amplitude::getInstance()
            ->queueEvent('User waited for SSH key upload', ['wait' => $wait]);
        return $wait;
    }
}
