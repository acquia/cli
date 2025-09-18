<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\SshKeys;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ssh-key:info', description: 'Print information about an SSH key')]
final class SshKeyInfoCommand extends SshKeyCommandBase
{
    protected function configure(): void
    {
        $this
            ->addOption('fingerprint', null, InputOption::VALUE_REQUIRED, 'sha256 fingerprint')
            ->addUsage('--fingerprint=pyarUa1mt2ln4fmrp7alWKpv1IPneqFwE+ErTC71IvY=');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $key = $this->determineSshKey($acquiaCloudClient);
        if (empty($key)) {
            throw new AcquiaCliException('No valid SSH key found.');
        }
        $location = 'Local';
        if (array_key_exists('cloud', $key)) {
            $location = array_key_exists('local', $key) ? 'Local + Cloud' : 'Cloud';
        }
        $this->io->definitionList(
            ['SSH key property' => 'SSH key value'],
            new TableSeparator(),
            ['Location' => $location],
            ['Fingerprint (sha256)' => $key['fingerprint']],
            ['Fingerprint (md5)' => array_key_exists('cloud', $key) ? $key['cloud']['fingerprint'] : 'n/a'],
            ['UUID' => array_key_exists('cloud', $key) ? $key['cloud']['uuid'] : 'n/a'],
            ['Label' => array_key_exists('cloud', $key) ? $key['cloud']['label'] : $key['local']['filename']],
            ['Created at' => array_key_exists('cloud', $key) ? $key['cloud']['created_at'] : 'n/a'],
        );

        $this->io->writeln("Public key\n----------");
        $this->io->writeln($key['public_key']);
        return Command::SUCCESS;
    }

    /**
     * @return array<mixed>
     */
    private function determineSshKey(mixed $acquiaCloudClient): array
    {
        $cloudKeysResponse = new SshKeys($acquiaCloudClient);
        $cloudKeys = $cloudKeysResponse->getAll();
        $localKeys = $this->findLocalSshKeys();
        $keys = [];
        /** @var \AcquiaCloudApi\Response\SshKeyResponse $key */
        foreach ($cloudKeys as $key) {
            $fingerprint = self::getFingerprint($key->public_key);
            if (!empty($fingerprint)) {
                $keys[$fingerprint]['fingerprint'] = $fingerprint;
                $keys[$fingerprint]['public_key'] = $key->public_key;
                $keys[$fingerprint]['cloud'] = [
                    'created_at' => $key->created_at,
                    'fingerprint' => $key->fingerprint,
                    'label' => $key->label,
                    'uuid' => $key->uuid,
                ];
            }
        }
        foreach ($localKeys as $key) {
            $fingerprint = self::getFingerprint($key->getContents());
            if (!empty($fingerprint)) {
                $keys[$fingerprint]['fingerprint'] = $fingerprint;
                $keys[$fingerprint]['public_key'] = $key->getContents();
                $keys[$fingerprint]['local'] = [
                    'filename' => $key->getFilename(),
                ];
            }
        }
        if ($fingerprint = $this->input->getOption('fingerprint')) {
            if (!array_key_exists($fingerprint, $keys)) {
                throw new AcquiaCliException('No key exists matching provided fingerprint');
            }
            return $keys[$fingerprint];
        }
        if (count($keys) > 0) {
            return $this->promptChooseFromObjectsOrArrays(
                $keys,
                'fingerprint',
                'fingerprint',
                'Choose an SSH key to view'
            );
        } else {
            return [];
        }
    }
}
