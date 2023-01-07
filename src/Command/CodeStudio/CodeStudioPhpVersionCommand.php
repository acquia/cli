<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Gitlab\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CodeStudioPhpVersionCommand extends CommandBase {

  use CodeStudioCommandTrait;

  protected static $defaultName = 'codestudio:php-version';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Change the PHP version in Code Studio')
      ->addArgument('php-version', InputArgument::REQUIRED, 'The PHP version that needs to configured or updated')
      ->addUsage(self::getDefaultName() . ' 8.1 myapp')
      ->addUsage(self::getDefaultName() . ' 8.1 abcd1234-1111-2222-3333-0e02b2c3d470');
    $this->acceptApplicationUuid();
    $this->acceptGitlabOptions();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $php_version = $input->getArgument('php-version');
    $this->validatePhpVersion($php_version);
    $this->authenticateWithGitLab();
    $acquiaCloudAppId = $input->getArgument('applicationUuid');

    // Get the gitlab project details attached with the given
    // cloud application.
    $cloud_application = $this->getCloudApplication($acquiaCloudAppId);
    $project = $this->determineGitLabProject($cloud_application);

    // if CI/CD is not enabled for the project in code studio.
    if (empty($project['jobs_enabled'])) {
      $this->io->error('CI/CD is not enabled for this application in code studio. Please enable it first and then try again.');
      return 1;
    }

    $php_version_already_set = FALSE;
    // Get all variables of the project.
    $all_project_variables = $this->gitLabClient->projects()->variables($project['id']);
    if (!empty($all_project_variables)) {
      $variables = array_column($all_project_variables, 'value', 'key');
      $php_version_already_set = $variables['PHP_VERSION'] ?? FALSE;
    }

    try {
      // If PHP version is not set in variables.
      if (!$php_version_already_set) {
        $this->gitLabClient->projects()->addVariable($project['id'], 'PHP_VERSION', $php_version);
      }
      else {
        // If variable already exists, updating the variable.
        $this->gitLabClient->projects()->updateVariable($project['id'], 'PHP_VERSION', $php_version);
      }
    }
    catch (RuntimeException $exception) {
      $this->io->error("Unable to update the PHP version to {$php_version}");
      return 1;
    }

    $this->io->success("PHP version is updated to {$php_version} successfully!");
    return 0;
  }

}
