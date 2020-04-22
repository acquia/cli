<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\Exception\AdsException;
use AcquiaCloudApi\Endpoints\Databases;
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @todo Identify a valid target, throw exception if not found.
        $this->validateCwdIsValidDrupalProject();

        // Choose remote environment.
        $cloud_application_uuid = $this->determineCloudApplication();
        // @todo Write name of Cloud application to screen.
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $environment_resource = new Environments($acquia_cloud_client);
        $application_environments = $environment_resource->getAll($cloud_application_uuid);
        $choices = [];
        foreach ($application_environments as $environment) {
            $choices[] = "{$environment->label} (vcs: {$environment->vcs->path})";
        }
        $question = new ChoiceQuestion('<question>Choose an Acquia Cloud environment to copy from</question>:',
          $choices);
        $helper = $this->getHelper('question');
        $chosen_environment_label = $helper->ask($this->input, $this->output, $question);
        foreach ($application_environments as $application_environment) {
            if ($application_environment->label === $chosen_environment_label) {
                $chosen_environment = $application_environment;
                break;
            }
        }

        // Git clone if no local repo found.
        // @todo This won't actually execute because of $this->validateCwdIsValidDrupalProject();
        // $this->pullCodeFromCloud($chosen_environment);

        // Copy databases.
        $response = $acquia_cloud_client->makeRequest('get', '/environments/' . $environment->uuid . '/databases');
        $databases = $acquia_cloud_client->processResponse($response);
        foreach ($databases as $database) {
            if ($database->flags->default) {
                $db_url_parts = explode('/', $database->url);
                $db_name = end($db_url_parts);
                // Workaround until db_host is fixed (CXAPI-7018).
                $db_host = $database->db_host ?: "db-${$db_name}.cdb.database.services.acquia.io";
            }
        }

        // SSH & dump.

        // Copy files.
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
            ], null, false);
        } else {
            $is_dirty = $this->getApplication()->getLocalMachineHelper()->execute([
              'git',
              'diff',
              '--stat',
            ], null, false, $repo_root);
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
}
