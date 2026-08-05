<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\SshKeys;
use AcquiaCloudApi\Response\EnvironmentResponse;
use FilesystemIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'setup', description: 'Set up a complete local development environment for an Acquia application')]
final class SetupCommand extends PullCommandBase
{
    use SshCommandTrait;

    protected function configure(): void
    {
        $this
            ->acceptEnvironmentId()
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'The directory to clone the application into (defaults to ./<application name>)')
            ->addUsage('myapp.dev --dir=./myapp --no-interaction')
            ->setHelp('This command takes you from nothing to a working local copy of an Acquia application: it authenticates with the Cloud Platform, helps you pick an application and environment, registers an SSH key if needed, clones your code, provisions a local stack with ddev, imports the database and files, and opens the site in your browser.'
                . "\n\nPrerequisites: git, Docker, and ddev (the command checks for these and tells you how to install anything missing)."
                . "\n\nEvery step is skipped automatically if it is already done, so if setup fails partway you can fix the problem and re-run <info>acli setup</info> to resume where it left off."
                . "\n\nFor non-interactive use (CI, scripts), pass the environment ID and credentials: <info>ACLI_KEY=... ACLI_SECRET=... acli setup myapp.dev --no-interaction</info>. This requires an SSH key already registered with the Cloud Platform.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->writeln(["<options=bold>Let's get you a local development environment.</>", '']);
        $this->checkPrerequisites();
        $this->ensureAuthenticated($input, $output);
        $environment = $this->determineEnvironment($input, $output);
        $this->ensureSshKey($input, $output);
        $this->dir = $this->determineTargetDirectory($input, $environment);
        $this->ensureCode($environment, $output);
        $this->linkApplication($environment);
        $this->ensureDdevConfigured($output);
        $this->startLocalEnvironment($output);
        $this->installComposerDependencies($output);
        if ($this->siteIsInstalled()) {
            $this->io->writeln('✓ Site database already present — skipping database and file sync. Run <options=bold>acli pull</> to refresh it.');
        } else {
            $dumpPaths = $this->pullDatabase($input, $output, $environment, false, true);
            $this->importDatabaseDumps($dumpPaths, $output);
            $this->pullFiles($input, $output, $environment);
            $this->refreshDrupal($output);
        }
        $url = $this->getLocalSiteUrl();
        if ($input->isInteractive() && $this->localMachineHelper->isBrowserAvailable()) {
            $this->localMachineHelper->startBrowser($url);
        }
        $this->printSummary($environment, $url);

        return Command::SUCCESS;
    }

    /**
     * Check for required tools and give one copy-pasteable remedy per missing
     * tool. PHP, Composer, Drush, and MySQL are NOT required on the host:
     * everything that needs them runs inside the ddev containers.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function checkPrerequisites(): void
    {
        $isMac = PHP_OS_FAMILY === 'Darwin';
        $remedies = [
            'ddev' => $isMac ? 'brew install ddev/ddev/ddev' : 'curl -fsSL https://ddev.com/install.sh | bash',
            'docker' => $isMac ? 'brew install --cask docker' : 'curl -fsSL https://get.docker.com | sudo sh',
            'git' => $isMac ? 'xcode-select --install' : 'sudo apt install git',
        ];
        $missing = [];
        foreach ($remedies as $binary => $remedy) {
            if (!$this->localMachineHelper->commandExists($binary)) {
                $missing[] = sprintf('  %-8s %s', $binary, $remedy);
            }
        }
        if ($missing) {
            throw new AcquiaCliException("Some required tools are missing. Install them with the commands below, then re-run acli setup:\n" . implode("\n", $missing));
        }
        $process = $this->localMachineHelper->execute(['docker', 'info'], null, null, false, 30);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Docker is installed but not running. Start your Docker provider (Docker Desktop, OrbStack, or `colima start`), then re-run acli setup.');
        }
        $this->io->writeln('✓ Found git, docker, and ddev');
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function ensureAuthenticated(InputInterface $input, OutputInterface $output): void
    {
        if (!$this->cloudApiClientService->isMachineAuthenticated()) {
            if (!$input->isInteractive()) {
                throw new AcquiaCliException('This machine is not authenticated with the Cloud Platform. Run `acli auth:login` first or set the ACLI_KEY and ACLI_SECRET environment variables.');
            }
            $this->io->writeln("First, let's connect to your Acquia Cloud Platform account.");
            $exitCode = $this->getApplication()->find('auth:login')->run(new ArrayInput(['command' => 'auth:login']), $output);
            if ($exitCode !== Command::SUCCESS) {
                throw new AcquiaCliException('Authentication failed.');
            }
        }
        $account = new Account($this->cloudApiClientService->getClient());
        $this->io->writeln('✓ Authenticated as <options=bold>' . $account->get()->mail . '</>');
    }

    /**
     * Ensure at least one local SSH key is registered with the Cloud Platform
     * so that git cloning and file syncing work.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function ensureSshKey(InputInterface $input, OutputInterface $output): void
    {
        $cloudKeys = (new SshKeys($this->cloudApiClientService->getClient()))->getAll();
        foreach ($this->findLocalSshKeys() as $localKey) {
            foreach ($cloudKeys as $cloudKey) {
                if ($this->publicKeysMatch($cloudKey->public_key, $localKey->getContents())) {
                    $this->io->writeln('✓ SSH key <options=bold>' . $localKey->getFilename() . '</> is registered with the Cloud Platform');
                    return;
                }
            }
        }
        // Keys may live only in an SSH agent (e.g. a forwarded agent or the
        // 1Password SSH agent) rather than as files in ~/.ssh.
        foreach ($this->findSshAgentKeys() as $agentKey) {
            foreach ($cloudKeys as $cloudKey) {
                if ($this->publicKeysMatch($cloudKey->public_key, $agentKey)) {
                    $this->io->writeln('✓ An SSH key in your SSH agent is registered with the Cloud Platform');
                    return;
                }
            }
        }
        if (!$input->isInteractive()) {
            throw new AcquiaCliException('No local SSH key is registered with the Cloud Platform. Run `acli ssh-key:create-upload` first.');
        }
        $this->io->writeln('You need an SSH key registered with the Cloud Platform to clone your application.');
        $exitCode = $this->getApplication()->find('ssh-key:create-upload')->run(new ArrayInput(['command' => 'ssh-key:create-upload']), $output);
        if ($exitCode !== Command::SUCCESS) {
            throw new AcquiaCliException('SSH key setup failed.');
        }
    }

    /**
     * @return string[] Public keys loaded into the SSH agent, if any.
     */
    private function findSshAgentKeys(): array
    {
        if (!$this->localMachineHelper->commandExists('ssh-add')) {
            return [];
        }
        $process = $this->localMachineHelper->execute(['ssh-add', '-L'], null, null, false);
        if (!$process->isSuccessful()) {
            return [];
        }
        return array_filter(explode("\n", trim($process->getOutput())));
    }

    /**
     * Compare only the key type and base64 material: the trailing comment may
     * legitimately differ between the agent and the Cloud Platform.
     */
    private function publicKeysMatch(string $a, string $b): bool
    {
        $material = static function (string $key): string {
            $parts = preg_split('/\s+/', trim($key));
            return $parts[0] . ' ' . ($parts[1] ?? '');
        };
        return $material($a) === $material($b);
    }

    private function determineTargetDirectory(InputInterface $input, EnvironmentResponse $environment): string
    {
        if ($dir = $input->getOption('dir')) {
            return Path::makeAbsolute(Path::canonicalize($dir), getcwd());
        }
        $cwd = getcwd();
        // Resuming inside an existing checkout of this application.
        if ($this->isEnvironmentCheckout($cwd, $environment)) {
            return $cwd;
        }
        return Path::join($cwd, self::getSitegroup($environment));
    }

    private function isEnvironmentCheckout(string $dir, EnvironmentResponse $environment): bool
    {
        $gitConfigPath = Path::join($dir, '.git', 'config');
        return file_exists($gitConfigPath) && str_contains($this->localMachineHelper->readFile($gitConfigPath), $environment->vcs->url);
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function ensureCode(EnvironmentResponse $environment, OutputInterface $output): void
    {
        if (is_dir(Path::join($this->dir, '.git'))) {
            if (!$this->isEnvironmentCheckout($this->dir, $environment)) {
                throw new AcquiaCliException('{dir} already contains a Git repository that is not a checkout of {url}. Use --dir to choose another directory.', [
                    'dir' => $this->dir,
                    'url' => $environment->vcs->url,
                ]);
            }
            $this->io->writeln('✓ Code already cloned to <options=bold>' . $this->dir . '</>');
            $this->projectDir = $this->dir;
            return;
        }
        if (is_dir($this->dir) && (new FilesystemIterator($this->dir))->valid()) {
            throw new AcquiaCliException('{dir} already exists and is not empty. Use --dir to choose another directory.', ['dir' => $this->dir]);
        }
        $this->checklist->addItem("Cloning the $environment->name environment's code into $this->dir");
        $this->cloneFromCloud($environment, $this->getOutputCallback($output, $this->checklist));
        $this->checklist->completePreviousItem();
    }

    /**
     * Link the checkout to its Cloud application so that later commands
     * (acli pull, acli push, ...) know which application this is.
     */
    private function linkApplication(EnvironmentResponse $environment): void
    {
        $configPath = Path::join($this->dir, '.acquia-cli.yml');
        if ($this->localMachineHelper->getFilesystem()->exists($configPath)) {
            return;
        }
        $this->localMachineHelper->getFilesystem()->dumpFile($configPath, Yaml::dump(['cloud_app_uuid' => $environment->application->uuid]));
        $this->io->writeln('✓ Linked project to your Cloud application');
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function ensureDdevConfigured(OutputInterface $output): void
    {
        if (file_exists(Path::join($this->dir, '.ddev', 'config.yaml'))) {
            $this->io->writeln('✓ ddev is already configured');
            return;
        }
        $this->checklist->addItem('Configuring ddev');
        $process = $this->localMachineHelper->execute(['ddev', 'config', '--auto'], $this->getOutputCallback($output, $this->checklist), $this->dir, false);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to configure ddev. {message}', ['message' => $process->getErrorOutput()]);
        }
        $this->checklist->completePreviousItem();
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

    /**
     * Install Composer dependencies inside the ddev web container so that
     * PHP and Composer are not required on the host.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function installComposerDependencies(OutputInterface $output): void
    {
        if (!file_exists(Path::join($this->dir, 'composer.json'))) {
            $this->io->writeln('✓ No composer.json found — skipping dependency install');
            return;
        }
        if (is_dir(Path::join($this->dir, 'vendor'))) {
            $this->io->writeln('✓ Composer dependencies already installed');
            return;
        }
        $this->checklist->addItem('Installing Composer dependencies (inside ddev)');
        $process = $this->localMachineHelper->execute(['ddev', 'composer', 'install'], $this->getOutputCallback($output, $this->checklist), $this->dir, false, null);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to install Composer dependencies. {message}', ['message' => $process->getErrorOutput()]);
        }
        $this->checklist->completePreviousItem();
    }

    /**
     * A fully bootstrappable Drupal site means the database was already
     * imported; re-running setup should not clobber it.
     */
    private function siteIsInstalled(): bool
    {
        $process = $this->localMachineHelper->execute(['ddev', 'drush', 'status', '--field=bootstrap'], null, $this->dir, false);
        return $process->isSuccessful() && str_contains($process->getOutput(), 'Successful');
    }

    /**
     * @param string[] $dumpPaths
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function importDatabaseDumps(array $dumpPaths, OutputInterface $output): void
    {
        $this->localMachineHelper->checkRequiredBinariesExist(['gunzip']);
        foreach ($dumpPaths as $dumpPath) {
            $this->checklist->addItem('Importing database into ddev');
            // Stream the dump through stdin: ddev's --file import stages the
            // dump via the .ddev bind mount, which is unreliable on some
            // Docker providers (e.g. colima).
            $process = $this->localMachineHelper->executeFromCmd('bash -o pipefail -c "gunzip -c \"$DUMP_FILEPATH\" | ddev import-db"', $this->getOutputCallback($output, $this->checklist), $this->dir, false, null, ['DUMP_FILEPATH' => $dumpPath]);
            if (!$process->isSuccessful()) {
                throw new AcquiaCliException('Unable to import database into ddev. {message}', ['message' => $process->getErrorOutput()]);
            }
            $this->checklist->completePreviousItem();
            $this->localMachineHelper->getFilesystem()->remove($dumpPath);
        }
    }

    /**
     * Rebuild caches and sanitize the database, like `acli pull` does. Not
     * fatal if it fails: the site is usually still usable.
     */
    private function refreshDrupal(OutputInterface $output): void
    {
        $this->checklist->addItem('Rebuilding Drupal caches and sanitizing the database');
        $callback = $this->getOutputCallback($output, $this->checklist);
        $cacheRebuild = $this->localMachineHelper->execute(['ddev', 'drush', 'cache:rebuild', '--yes'], $callback, $this->dir, false, null);
        $sanitize = $this->localMachineHelper->execute(['ddev', 'drush', 'sql:sanitize', '--yes'], $callback, $this->dir, false, null);
        $this->checklist->completePreviousItem();
        if (!$cacheRebuild->isSuccessful() || !$sanitize->isSuccessful()) {
            $this->io->warning('Could not run Drush post-install tasks. Your site may still work — try `ddev drush cache:rebuild` inside ' . $this->dir);
        }
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

    private function printSummary(EnvironmentResponse $environment, string $url): void
    {
        $this->io->success("Your local development environment is ready: $url");
        $this->io->writeln([
            'What you have:',
            "  Site:  $url",
            "  Code:  $this->dir (<options=bold>{$environment->vcs->path}</> branch, tracking the $environment->label environment)",
            '',
            'Next steps:',
            '  ddev drush uli   Get a one-time login link for your site',
            '  acli pull        Re-sync the database and files from Cloud',
            '  ddev stop        Stop the local environment',
        ]);
    }

    /**
     * ddev projects may use web/ as the docroot (e.g. Drupal CMS) instead of
     * Acquia's traditional docroot/.
     */
    protected function getLocalFilesDir(string $site): string
    {
        if (!is_dir(Path::join($this->dir, 'docroot')) && is_dir(Path::join($this->dir, 'web'))) {
            return Path::join($this->dir, 'web', 'sites', $site, 'files');
        }
        return parent::getLocalFilesDir($site);
    }
}
