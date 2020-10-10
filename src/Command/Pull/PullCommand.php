<?php

namespace Acquia\Cli\Command\Pull;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RefreshCommand.
 */
class PullCommand extends PullCommandBase {

  protected static $defaultName = 'pull:all';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setAliases(['refresh', 'pull'])
      ->setDescription('Copy code, database, and files from a Cloud Platform environment')
      ->addArgument('dir', InputArgument::OPTIONAL, 'The directory containing the Drupal project to be refreshed')
      ->addOption('cloud-env-uuid', 'from', InputOption::VALUE_REQUIRED, 'The UUID of the associated Cloud Platform source environment')
      ->addOption('no-code', NULL, InputOption::VALUE_NONE, 'Do not refresh code from remote repository')
      ->addOption('no-files', NULL, InputOption::VALUE_NONE, 'Do not refresh files')
      ->addOption('no-databases', NULL, InputOption::VALUE_NONE, 'Do not refresh databases')
      ->addOption(
            'no-scripts',
            NULL,
            InputOption::VALUE_NONE,
            'Do not run any additional scripts after code and database are copied. E.g., composer install , drush cache-rebuild, etc.'
        )
      ->addOption('scripts', NULL, InputOption::VALUE_NONE, 'Only execute additional scripts');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->determineDir($input);
    if ($this->dir !== '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      throw new AcquiaCliException('Please run this command from the {dir} directory', ['dir' => '/home/ide/project']);
    }

    $clone = $this->determineCloneProject($output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $chosen_environment = $this->determineEnvironment($input, $output, $acquia_cloud_client);
    $checklist = new Checklist($output);
    $output_callback = static function ($type, $buffer) use ($checklist, $output) {
      if (!$output->isVerbose()) {
        $checklist->updateProgressBar($buffer);
      }
      $output->writeln($buffer, OutputInterface::VERBOSITY_VERY_VERBOSE);
    };

    if (!$input->getOption('no-code')) {
      if ($clone) {
        $checklist->addItem('Cloning git repository from the Cloud Platform');
        $this->cloneFromCloud($chosen_environment, $output_callback);
        $checklist->completePreviousItem();
      }
      else {
        $checklist->addItem('Pulling code from the Cloud Platform');
        $this->pullCodeFromCloud($chosen_environment, $output_callback);
        $checklist->completePreviousItem();
      }
    }

    // Copy databases.
    if (!$input->getOption('no-databases')) {
      $database = $this->determineSourceDatabase($acquia_cloud_client, $chosen_environment);
      $checklist->addItem('Importing Drupal database copy from the Cloud Platform');
      $this->importRemoteDatabase($chosen_environment, $database, $output_callback);
      $checklist->completePreviousItem();
    }

    // Copy files.
    if (!$input->getOption('no-files')) {
      $checklist->addItem('Copying Drupal\'s public files from the Cloud Platform');
      $this->rsyncFilesFromCloud($chosen_environment, $output_callback);
      $checklist->completePreviousItem();
    }

    if (!$input->getOption('no-scripts')) {
      if (file_exists($this->dir . '/composer.json') && $this->localMachineHelper
        ->commandExists('composer')) {
        $checklist->addItem('Installing Composer dependencies');
        $this->composerInstall($output_callback);
        $checklist->completePreviousItem();
      }

      if ($this->drushHasActiveDatabaseConnection($output_callback)) {
        // Drush rebuild caches.
        $checklist->addItem('Clearing Drupal caches via Drush');
        $this->drushRebuildCaches($output_callback);
        $checklist->completePreviousItem();

        // Drush sanitize.
        $checklist->addItem('Sanitizing database via Drush');
        $this->drushSqlSanitize($output_callback);
        $checklist->completePreviousItem();
      }
    }

    // Match IDE PHP version to source environment PHP version.
    $this->matchIdePhpVersion($output, $chosen_environment);

    return 0;
  }

}
