<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\Exception\AdsException;
use Acquia\Ads\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\ClientInterface;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class RefreshCommand.
 */
class RefreshCommand extends CommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('refresh')
          ->setDescription('Copy code, database, and files from an Acquia Cloud environment')
          ->addOption('from', null, InputOption::VALUE_NONE, 'The source environment')
          ->addOption('no-code', null, InputOption::VALUE_NONE, 'Do not refresh code from remote repository')
          ->addOption('no-files', null, InputOption::VALUE_NONE, 'Do not refresh files')
          ->addOption('no-databases', null, InputOption::VALUE_NONE, 'Do not refresh databases')
          ->addOption('no-scripts', null, InputOption::VALUE_NONE,
            'Do not run any additional scripts after code and database are copied. E.g., composer install , drush cache-rebuild, etc.')
          ->addOption('scripts', null, InputOption::VALUE_NONE, 'Only execute additional scripts');
        // @todo Add option to allow specifying source environment.
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @todo Identify a valid target, throw exception if not found.
        $this->validateCwdIsValidDrupalProject();

        // Choose remote environment.
        $cloud_application_uuid = $this->determineCloudApplication();
        // @todo Write name of Cloud application to screen.
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $chosen_environment = $this->promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid);
        $checklist = new Checklist($output);

        // Git clone if no local repo found.
        // @todo This won't actually execute if repo is missing because of $this->validateCwdIsValidDrupalProject();
        if (!$input->getOption('no-code')) {
            $checklist->addItem('Pulling code from Acquia Cloud');
            $this->pullCodeFromCloud($chosen_environment);
            $checklist->completePreviousItem();
        }

        // Copy databases.
        if (!$input->getOption('no-databases')) {
            $checklist->addItem('Importing Drupal default database copy from Acquia Cloud');
            $this->importDatabaseFromEnvironment($acquia_cloud_client, $chosen_environment);
            $checklist->completePreviousItem();
        }

        // Copy files.
        if (!$input->getOption('no-files')) {
            $checklist->addItem('Copying Drupal\'s public files from Acquia Cloud');
            $this->rsyncFilesFromCloud($chosen_environment);
            $checklist->completePreviousItem();
        }

        if (!$input->getOption('no-scripts')) {
            if (file_exists($this->getApplication()->getRepoRoot() . '/composer.json') && $this->getApplication()
                ->getLocalMachineHelper()
                ->commandExists('composer')) {
                $checklist->addItem('Installing Composer dependencies');
                $this->composerInstall();
                $checklist->completePreviousItem();
            }

            if ($this->drushHasActiveDatabaseConnection()) {
                // Drush rebuild caches.
                $checklist->addItem('Clearing Drupal caches via Drush');
                $this->drushRebuildCaches();
                $checklist->completePreviousItem();

                // Drush sanitize.
                $checklist->addItem('Sanitizing database via Drush');
                $this->drushSqlSanitize();
                $checklist->completePreviousItem();
            }
        }

        return 0;
    }

    /**
     * @return bool
     */
    protected function drushHasActiveDatabaseConnection(): bool
    {
        if ($this->getApplication()->getLocalMachineHelper()->commandExists('drush')) {
            $drush_status_return = $this->getApplication()->getLocalMachineHelper()->execute([
              'drush',
              'status',
              '--fields=db-status,drush-version',
              '--format=json',
              '--no-interaction',
            ], null, null, false);
            if ($drush_status_return['exit_code'] === 0) {
                $drush_status_return_output = json_decode($drush_status_return['output'], TRUE);
                if (is_array($drush_status_return_output) && array_key_exists('db-status', $drush_status_return_output) && $drush_status_return_output['db-status'] === 'Connected') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $chosen_environment
     *
     * @throws \Acquia\Ads\Exception\AdsException
     */
    protected function pullCodeFromCloud($chosen_environment): void
    {
        $repo_root = $this->getApplication()->getRepoRoot();
        if (!file_exists($repo_root . '/.git')) {
            $this->getApplication()->getLocalMachineHelper()->execute([
              'git',
              'clone',
              $chosen_environment->vcs->url,
              $this->getApplication()->getRepoRoot(),
            ]);
        } else {
            $is_dirty = $this->isLocalGitRepoDirty($repo_root);
            if ($is_dirty) {
                throw new AdsException('Local git is dirty!');
            }

            $this->getApplication()->getLocalMachineHelper()->execute([
              'git',
              'fetch',
              $chosen_environment->vcs_url,
              $this->getApplication()->getRepoRoot(),
            ], null, $repo_root);
            $this->getApplication()->getLocalMachineHelper()->execute([
              'git',
              'checkout',
              $chosen_environment->vcs_url,
              $this->getApplication()->getRepoRoot(),
            ], null, $repo_root);
        }
    }

    /**
     * @param $environment
     * @param $database
     * @param string $db_host
     * @param $db_name
     */
    protected function createAndImportRemoteDatabaseDump($environment, $database, string $db_host, $db_name): void
    {
        $mysql_dump_filepath = $this->dumpFromRemoteHost($environment, $database, $db_host, $db_name);

        // @todo Determine this dynamically?
        $local_db_host = 'localhost';
        $local_db_user = 'drupal';
        $local_db_name = 'drupal';
        $local_db_password = 'drupal';
        $this->dropLocalDatabase($local_db_host, $local_db_user, $local_db_name, $local_db_password);
        $this->createLocalDatabase($local_db_host, $local_db_user, $local_db_name, $local_db_password);
        $this->importDatabaseDump($mysql_dump_filepath, $local_db_host, $local_db_user, $local_db_name,
          $local_db_password);
    }

    /**
     * @param $environment
     * @param $database
     * @param string $db_host
     * @param $db_name
     *
     * @return string|null
     */
    protected function dumpFromRemoteHost($environment, $database, string $db_host, $db_name): ?string
    {
        $process = $this->getApplication()->getLocalMachineHelper()->exec([
          'ssh',
          '-T',
          '-o',
          'StrictHostKeyChecking no',
          '-o',
          'LogLevel=ERROR',
          $environment->sshUrl,
          "MYSQL_PWD={$database->password} mysqldump --host={$db_host} --user={$database->user_name} {$db_name} | gzip -9",
        ]);

        if ($process->isSuccessful()) {
            $filepath = $this->fs->tempnam(sys_get_temp_dir(), $environment->uuid . '_mysqldump_');
            $filepath .= '.sql.gz';
            $this->fs->dumpFile($filepath, $process->getOutput());

            return $filepath;
        }

        return null;
    }

    /**
     * @param string $db_host
     * @param string $db_user
     * @param string $db_name
     * @param string $db_password
     */
    protected function dropLocalDatabase($db_host, $db_user, $db_name, $db_password): void
    {
        $this->getApplication()->getLocalMachineHelper()->exec([
          'mysql',
          '--host',
          $db_host,
          '--user',
          $db_user,
            // @todo Is this insecure in any way?
          '--password=' . $db_password,
          '-e',
          'DROP DATABASE IF EXISTS ' . $db_name,
        ]);
    }

    /**
     * @param string $db_host
     * @param string $db_user
     * @param string $db_name
     * @param string $db_password
     */
    protected function createLocalDatabase($db_host, $db_user, $db_name, $db_password): void
    {
        $this->getApplication()->getLocalMachineHelper()->exec([
          'mysql',
          '--host',
          $db_host,
          '--user',
          $db_user,
            // @todo Is this insecure in any way?
          '--password=' . $db_password,
          '-e',
          'create database ' . $db_name,
        ]);
    }

    /**
     * @param string $dump_filepath
     * @param string $db_host
     * @param string $db_user
     * @param string $db_name
     * @param string $db_password
     */
    protected function importDatabaseDump($dump_filepath, $db_host, $db_user, $db_name, $db_password): void
    {
        // Unfortunately we need to make this a string to prevent the '|' characters from being escaped.
        // @see https://github.com/symfony/symfony/issues/10025.
        $command = '';
        if ($this->getApplication()->getLocalMachineHelper()->commandExists('pv')) {
            $command .= 'pv ';
        } else {
            $command .= 'cat ';
        }
        $command .= "$dump_filepath | ";

        $dump_file_parts = pathinfo($dump_filepath);
        if ($dump_file_parts['extension'] === 'gz') {
            $command .= 'gunzip | ';
        }

        $command .= "MYSQL_PWD=$db_password mysql --host=$db_host --user=$db_user $db_name";

        $this->getApplication()->getLocalMachineHelper()->executeFromCmd($command);
    }

    /**
     * @param string|null $repo_root
     *
     * @return array
     */
    protected function isLocalGitRepoDirty(?string $repo_root): array
    {
        $is_dirty = $this->getApplication()->getLocalMachineHelper()->execute([
          'git',
          'diff',
          '--stat',
        ], null, $repo_root, false);

        return $is_dirty;
    }

    /**
     * @param $acquia_cloud_client
     * @param string $cloud_application_uuid
     *
     * @return mixed
     */
    protected function promptChooseEnvironment($acquia_cloud_client, $cloud_application_uuid)
    {
        $environment_resource = new Environments($acquia_cloud_client);
        $application_environments = iterator_to_array($environment_resource->getAll($cloud_application_uuid));
        $choices = [];
        foreach ($application_environments as $environment) {
            // Don't allow a refresh from prod.
            if (!$environment->flags->production) {
                $choices[] = "{$environment->label} (vcs: {$environment->vcs->path})";
            }
        }
        $question = new ChoiceQuestion('<question>Choose an Acquia Cloud environment to copy from</question>:',
          $choices);
        $helper = $this->getHelper('question');
        $chosen_environment_label = $helper->ask($this->input, $this->output, $question);
        $chosen_environment_index = array_search($chosen_environment_label, $choices, true);

        return $application_environments[$chosen_environment_index];
    }

    /**
     * @param $acquia_cloud_client
     * @param \AcquiaCloudApi\Response\EnvironmentResponse $cloud_environment
     *
     * @return mixed
     * @throws \Exception
     */
    protected function promptChooseDatabase(ClientInterface $acquia_cloud_client, $cloud_environment)
    {
        $response = $acquia_cloud_client->makeRequest('get', '/environments/' . $cloud_environment->uuid . '/databases');
        $environment_databases = $acquia_cloud_client->processResponse($response);
        $choices = [];
        $default_database_index = 0;
        foreach ($environment_databases as $index => $database) {
            // @todo For ACSF, map database name to site name from
            // /var/www/site-php/<sitegroup>.<env>/multisite-config.json.
            if ($database->flags->default) {
                $default_database_index = $index;
                $choices[] = $database->name . ' (default)';
            }
            else {
                $choices[] = $database->name;
            }
        }
        $question = new ChoiceQuestion('<question>Choose a database to copy</question>:',
          $choices, $default_database_index);
        $helper = $this->getHelper('question');
        $chosen_database_label = $helper->ask($this->input, $this->output, $question);
        $chosen_database_index = array_search($chosen_database_label, $choices, true);

        return $environment_databases[$chosen_database_index];
    }

    /**
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     * @param $chosen_environment
     *
     * @throws \Exception
     */
    protected function importDatabaseFromEnvironment(
      Client $acquia_cloud_client,
      $chosen_environment
    ): void {
        $response = $acquia_cloud_client->makeRequest('get',
          '/environments/' . $chosen_environment->uuid . '/databases');
        $databases = $acquia_cloud_client->processResponse($response);
        if (count($databases) > 1) {
            $database = $this->promptChooseDatabase($acquia_cloud_client, $chosen_environment);
        }
        else {
            $database = reset($databases);
        }
        $db_url_parts = explode('/', $database->url);
        $db_name = end($db_url_parts);
        // Workaround until db_host is fixed (CXAPI-7018).
        $db_host = $database->db_host ?: "db-${$db_name}.cdb.database.services.acquia.io";
        $this->createAndImportRemoteDatabaseDump($chosen_environment, $database, $db_host, $db_name);
    }

    protected function drushRebuildCaches(): void
    {
// @todo Add support for Drush 8.
        $this->getApplication()->getLocalMachineHelper()->execute([
          'drush',
          'cache:rebuild',
          '--yes',
          '--no-interaction',
        ], null, $this->getApplication()->getRepoRoot(), false);
    }

    protected function drushSqlSanitize(): void
    {
        $this->getApplication()->getLocalMachineHelper()->execute([
          'drush',
          'sql:sanitize',
          '--yes',
          '--no-interaction',
        ], null, $this->getApplication()->getRepoRoot(), false);
    }

    protected function composerInstall(): void
    {
        $this->getApplication()->getLocalMachineHelper()->execute([
          'composer',
          'install',
          '--no-interaction'
        ], null, $this->getApplication()->getRepoRoot(), false);
    }

    /**
     * @param $chosen_environment
     */
    protected function rsyncFilesFromCloud($chosen_environment): void
    {
        $this->getApplication()->getLocalMachineHelper()->execute([
          'rsync',
          '-rve',
          'ssh -o StrictHostKeyChecking=no',
          $chosen_environment->sshUrl . ':/' . $chosen_environment->name . '/sites/default/files',
          $this->getApplication()->getRepoRoot() . '/docroot/sites/default',
        ], null, null, false);
    }
}
