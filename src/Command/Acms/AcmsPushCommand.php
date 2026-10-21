<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Acms;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\Remote\SshBaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Pushes a local config directory to an environment over SSH.
 *
 * This is the packaged form of the documented `config:import-stream` pipeline:
 *
 * @code
 * tar -cz -C ./partial-config . | acli remote:drush myapp.dev -- config:import-stream
 * @endcode
 *
 * Rather than asking the user to build the tar stream and pipe it in, the
 * command builds the payload itself and feeds it to the remote Drush over the
 * same SSH transport that `remote:drush` uses.
 *
 * That is all it does. Taking the site down for the import, checking the
 * payload against the site's allowlist and retiring content types it no longer
 * declares all belong to `config:import-stream`, which runs on the environment
 * and therefore applies to every caller, not just this one.
 */
#[RequireAuth]
#[AsCommand(name: 'acms:push', description: 'Push a local config directory to a Cloud Platform environment')]
final class AcmsPushCommand extends SshBaseCommand
{
    protected function configure(): void
    {
        // The environment argument is declared here rather than via
        // acceptEnvironmentId() so that it follows config_dir, and so that the
        // usage examples show both arguments in the right order.
        $this
            ->addArgument('config_dir', InputArgument::REQUIRED, 'Path to the directory of config YAML files to push')
            ->addArgument('environmentId', InputArgument::OPTIONAL, 'The Cloud Platform environment ID or alias (i.e. an application and environment name optionally prefixed with the realm)')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Push to the local DDEV site instead of a Cloud Platform environment')
            ->addOption('ddev-dir', null, InputOption::VALUE_REQUIRED, 'Path to the DDEV project to push to. Defaults to the DDEV project containing the current directory.')
            ->addUsage('./partial-config --local')
            ->addUsage('./partial-config')
            ->addUsage('./partial-config myapp.dev')
            ->addUsage('./partial-config prod:myapp.dev');
    }

    /**
     * Pushing to DDEV never talks to the Cloud API, so skip the auth check.
     */
    protected function checkAuthentication(): void
    {
        if ($this->input->getOption('local')) {
            return;
        }
        parent::checkAuthentication();
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configDir = $input->getArgument('config_dir');
        if (!is_dir($configDir)) {
            throw new AcquiaCliException('The config directory {dir} does not exist', ['dir' => $configDir]);
        }
        $this->localMachineHelper->checkRequiredBinariesExist(['tar']);

        if ($input->getOption('local')) {
            return $this->pushToDdev($configDir, $this->determineDdevDir($input));
        }

        $environment = $this->determineEnvironment($input, $output, true);
        $alias = self::getEnvironmentAlias($environment);

        $this->io->text(sprintf('Pushing config from %s to %s', $configDir, $alias));

        return $this->pushConfig($configDir, $this->remoteDrush($environment, $alias));
    }

    /**
     * Returns a callable that runs a Drush command in the environment's docroot.
     *
     * @return callable(array<int, string>, bool, ?string): \Symfony\Component\Process\Process
     */
    private function remoteDrush(object $environment, string $alias): callable
    {
        return function (array $drushArguments, bool $printOutput, ?string $stdin) use ($environment, $alias): Process {
            // When available, provide the default domain to drush.
            if (!empty($environment->default_domain)) {
                // Insert at the beginning so a user-supplied --uri arg will override.
                array_unshift($drushArguments, "--uri=http://$environment->default_domain");
            }
            $drushCommandArguments = [
                "cd /var/www/html/$alias/docroot; ",
                'drush',
                implode(' ', $drushArguments),
            ];

            return $this->sshHelper->executeCommand($environment->sshUrl, $drushCommandArguments, $printOutput, null, null, $stdin);
        };
    }

    /**
     * Streams the config to the target's config:import-stream.
     *
     * Everything that has to happen around the import — maintenance mode, the
     * allowlist, retiring content types the payload has dropped — is the
     * remote command's business, not this one's. It runs on the environment,
     * so it holds whether or not the caller's acli was built from this source.
     *
     * @param callable(array<int, string>, bool, ?string): \Symfony\Component\Process\Process $drush
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function pushConfig(string $configDir, callable $drush): int
    {
        $payload = $this->buildPayload($configDir);

        return $drush(['config:import-stream'], true, $payload)->getExitCode();
    }

    /**
     * Locates the DDEV project to push to.
     *
     * `ddev drush` only resolves the project's commands when it runs inside
     * that project, so this must not fall back to the current directory: the
     * config directory being pushed is frequently somewhere else entirely.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function determineDdevDir(InputInterface $input): string
    {
        $start = $input->getOption('ddev-dir') ?: ($this->projectDir ?: (string) getcwd());
        if (!is_dir($start)) {
            throw new AcquiaCliException('The directory {dir} does not exist', ['dir' => $start]);
        }

        // Climb towards the filesystem root looking for the DDEV project.
        $dir = realpath($start) ?: $start;
        for ($i = 0; $i <= 10; $i++) {
            if (file_exists($dir . '/.ddev/config.yaml')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new AcquiaCliException('Could not find a DDEV project in or above {dir}. Run this command from your DDEV project, or pass --ddev-dir.', ['dir' => $start]);
    }

    /**
     * Streams the payload to the local DDEV site.
     *
     * `ddev drush` stands in for the SSH hop: the payload still reaches
     * config:import-stream on stdin, so the command under test is the same one
     * that runs on a Cloud environment.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function pushToDdev(string $configDir, string $ddevDir): int
    {
        $this->localMachineHelper->checkRequiredBinariesExist(['ddev']);
        $this->io->text(sprintf('Pushing config from %s to the DDEV site at %s', $configDir, $ddevDir));

        return $this->pushConfig($configDir, $this->ddevDrush($ddevDir));
    }

    /**
     * Returns a callable that runs a Drush command inside the DDEV project.
     *
     * @return callable(array<int, string>, bool, ?string): \Symfony\Component\Process\Process
     */
    private function ddevDrush(string $ddevDir): callable
    {
        return fn (array $drushArguments, bool $printOutput, ?string $stdin): Process => $this->localMachineHelper->execute(
            array_merge(['ddev', 'drush'], $drushArguments),
            null,
            $ddevDir,
            $printOutput,
            null,
            null,
            true,
            $stdin
        );
    }

    /**
     * Builds the gzipped tar that the remote command reads from stdin.
     *
     * Archiving the directory's contents (`-C <dir> .`) rather than the
     * directory itself is what config:import-stream expects.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function buildPayload(string $configDir): string
    {
        $process = $this->localMachineHelper->execute(['tar', '-cz', '-C', $configDir, '.'], null, null, false);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to archive {dir}: {error}', [
                'dir' => $configDir,
                'error' => $process->getErrorOutput(),
            ]);
        }

        return $process->getOutput();
    }
}
