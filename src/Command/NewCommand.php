<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * Class NewCommand.
 */
class NewCommand extends CommandBase {

  protected static $defaultName = 'new';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Create a new Drupal project')
      ->addOption('distribution', NULL, InputOption::VALUE_REQUIRED, 'The composer package name of the Drupal distribution to download')
      ->addArgument('directory', InputArgument::OPTIONAL, 'The destination directory');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->output->writeln('Acquia recommends most customers use <options=bold>acquia/drupal-recommended-project</>, which includes useful utilities such as Acquia Connector.');
    $this->output->writeln('<options=bold>acquia/drupal-minimal-project</> is the most minimal application that will run on the Cloud Platform.');
    $distros = [
      'acquia/drupal-recommended-project',
      'acquia/drupal-minimal-project',
    ];
    $project = $this->io->choice('Choose a starting project', $distros, $distros[0]);

    if ($input->hasArgument('directory') && $input->getArgument('directory')) {
      $dir = Path::canonicalize($input->getArgument('directory'));
      $dir = Path::makeAbsolute($dir, getcwd());
    }
    else if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
      $dir = '/home/ide/project';
    } else {
      $dir = Path::makeAbsolute('drupal', getcwd());
    }

    $output->writeln('<info>Creating project. This may take a few minutes</info>');
    $this->createProject($project, $dir);

    if (strpos($project, 'acquia/drupal-minimal-project') !== FALSE) {
      $this->requireDrush($dir);
    }

    // We've deferred all installation until now.
    $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
    $this->localMachineHelper->execute([
      'composer',
      'update',
    ], NULL, $dir);

    // @todo Add a .gitignore and other recommended default files.
    $this->initializeGitRepository($dir);

    $output->writeln('');
    $output->writeln("<info>New 💧 Drupal project created in $dir. 🎉");

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * @param string $dir
   *
   * @throws \Exception
   */
  protected function requireDrush(string $dir): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
    $this->localMachineHelper->execute([
      'composer',
      'require',
      'drush/drush',
      '--no-update',
    ], NULL, $dir);
    // @todo Check that this was successful!
  }

  /**
   * @param $project
   * @param string $dir
   *
   * @throws \Exception
   */
  protected function createProject($project, string $dir): void {
    $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
    $process = $this->localMachineHelper->execute([
      'composer',
      'create-project',
      '--no-install',
      $project,
      $dir,
    ]);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Unable to create new project.");
    }
  }

  /**
   * @param string $dir
   *
   * @throws \Exception
   */
  protected function initializeGitRepository(string $dir): void {
    if ($this->localMachineHelper->getFilesystem()->exists(Path::join($dir, '.git'))) {
      $this->logger->debug('.git directory detected, skipping Git repo initialization');
      return;
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->localMachineHelper->execute([
      'git',
      'init',
    ], NULL, $dir);

    $this->localMachineHelper->execute([
      'git',
      'add',
      '-A',
    ], NULL, $dir);

    $this->localMachineHelper->execute([
      'git',
      'commit',
      '--message',
      'Initial commit.',
      '--quiet',
    ], NULL, $dir);
    // @todo Check that this was successful!
  }

}
