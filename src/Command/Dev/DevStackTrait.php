<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Dev;

use Acquia\Cli\Exception\AcquiaCliException;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Shared helpers for the dev:* commands that manage the ddev-based local
 * environment.
 */
trait DevStackTrait
{
    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function resolveProjectDir(InputInterface $input): string
    {
        $dir = $input->getOption('dir') ? Path::makeAbsolute(Path::canonicalize($input->getOption('dir')), getcwd()) : getcwd();
        if (!file_exists(Path::join($dir, '.ddev', 'config.yaml'))) {
            throw new AcquiaCliException('No local environment found in {dir}. Run this command from your project directory, or run `acli dev:init` to create one.', ['dir' => $dir]);
        }
        return $dir;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function startLocalEnvironment(OutputInterface $output): void
    {
        $this->checklist->addItem('Starting ddev (the first run may download Docker images)');
        $process = $this->localMachineHelper->execute(['ddev', 'start', '-y'], $this->getOutputCallback($output, $this->checklist), $this->dir, false, null);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to start ddev. {message}', ['message' => $process->getErrorOutput()]);
        }
        $this->checklist->completePreviousItem();
    }

    private function getLocalSiteUrl(): string
    {
        $process = $this->localMachineHelper->execute(['ddev', 'describe', '-j'], null, $this->dir, false);
        if ($process->isSuccessful()) {
            $json = json_decode($process->getOutput(), true);
            if (is_array($json) && isset($json['raw']['primary_url'])) {
                return $json['raw']['primary_url'];
            }
        }
        return 'https://' . basename($this->dir) . '.ddev.site';
    }

    /**
     * Verify the site actually serves before telling the user it is ready.
     * A warning, not a failure: some sites legitimately need extra local
     * steps, and everything else has already succeeded.
     */
    private function checkSiteResponds(string $url): void
    {
        try {
            $status = $this->httpClient->request('GET', $url, [
                'http_errors' => false,
                'timeout' => 30,
                'verify' => false,
            ])->getStatusCode();
        } catch (Exception) {
            $status = 0;
        }
        if ($status === 0 || $status >= 400) {
            $this->io->warning("The site did not respond as expected at $url (HTTP " . ($status ?: 'no response') . '). The stack is up, but the site may need attention: check `ddev logs -s web` and try `ddev drush uli`.');
        }
    }
}
