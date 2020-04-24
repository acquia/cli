<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
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
          ->addOption('code', null, InputOption::VALUE_NONE, 'Copy only code from remote repository')
          ->addOption('files', null, InputOption::VALUE_NONE, 'Copy only files from remote Acquia Cloud environment')
          ->addOption('databases', null, InputOption::VALUE_NONE,
            'Copy only databases from remote Acquia Cloud environment')
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

        // Git clone if no local repo found.
        // @todo This won't actually execute if repo is missing because of $this->validateCwdIsValidDrupalProject();
        $checklist = new Checklist($output);
        $checklist->addItem('Pulling code from Acquia Cloud');
        // $this->pullCodeFromCloud($chosen_environment);
        $checklist->completePreviousItem();

        // Copy databases.
        $checklist->addItem('Importing database copy from Acquia Cloud');
        $this->importDatabaseFromEnvironment($acquia_cloud_client, $chosen_environment);
        $checklist->completePreviousItem();

        // Copy files.
        $checklist->addItem('Copying public files from Acquia Cloud');
        $checklist->completePreviousItem();
        // Composer install.
        // Drush sanitize.
        // Drush rebuild caches.

        return 0;
    }

    /**
     * @param $chosen_environment
     */
    protected function pullCodeFromCloud($chosen_environment): void
    {
        $repo_root = $this->getApplication()->getRepoRoot();
        if ($repo_root . '/.git') {
            $this->getApplication()->getLocalMachineHelper()->execute([
              'git',
              'clone',
              $chosen_environment->vcs_url,
              $this->getApplication()->getRepoRoot(),
            ]);
        } else {
            $is_dirty = $this->isLocalGitRepoDirty($repo_root);
            if (!$is_dirty) {
                $this->getApplication()->getLocalMachineHelper()->execute([
                  'git',
                  'fetch',
                  $chosen_environment->vcs_url,
                  $this->getApplication()->getRepoRoot(),
                ], null, false, $repo_root);
                $this->getApplication()->getLocalMachineHelper()->execute([
                  'git',
                  'checkout',
                  $chosen_environment->vcs_url,
                  $this->getApplication()->getRepoRoot(),
                ], null, false, $repo_root);
            }
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
     * @param $db_host
     * @param $db_user
     * @param $db_name
     * @param $db_password
     */
    protected function dropLocalDatabase($db_host, $db_user, $db_name, $db_password): void
    {
        $this->getApplication()->getLocalMachineHelper()->execute([
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
     * @param $db_host
     * @param $db_user
     * @param $db_name
     * @param $db_password
     */
    protected function createLocalDatabase($db_host, $db_user, $db_name, $db_password): void
    {
        $this->getApplication()->getLocalMachineHelper()->execute([
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
     * @param $dump_filepath
     * @param $db_host
     * @param $db_user
     * @param $db_name
     * @param $db_password
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
        ], null, false, $repo_root);

        return $is_dirty;
    }

    /**
     * @param $acquia_cloud_client
     * @param string|null $cloud_application_uuid
     *
     * @return mixed
     */
    protected function promptChooseEnvironment($acquia_cloud_client, ?string $cloud_application_uuid)
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
        foreach ($databases as $database) {
            if ($database->flags->default) {
                $db_url_parts = explode('/', $database->url);
                $db_name = end($db_url_parts);
                // Workaround until db_host is fixed (CXAPI-7018).
                $db_host = $database->db_host ?: "db-${$db_name}.cdb.database.services.acquia.io";
                $this->createAndImportRemoteDatabaseDump($chosen_environment, $database, $db_host, $db_name);
                break;
            }
        }
    }
}
