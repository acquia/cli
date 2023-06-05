<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Gitlab\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CodeStudioPhpVersionCommand extends CommandBase {

  use CodeStudioCommandTrait;

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'codestudio:php-version';

  protected function configure(): void {
    $this->setDescription('Change the PHP version in Code Studio')
      ->addArgument('php-version', InputArgument::REQUIRED, 'The PHP version that needs to configured or updated')
      ->addUsage('8.1 myapp')
      ->addUsage('8.1 abcd1234-1111-2222-3333-0e02b2c3d470');
    $this->acceptApplicationUuid();
    $this->acceptGitlabOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $phpVersion = $input->getArgument('php-version');
    $this->validatePhpVersion($phpVersion);
    $this->authenticateWithGitLab();
    $acquiaCloudAppId = $this->determineCloudApplication();

    // Get the GitLab project details attached with the given Cloud application.
    $cloudApplication = $this->getCloudApplication($acquiaCloudAppId);
    $project = $this->determineGitLabProject($cloudApplication);

    // If CI/CD is not enabled for the project in Code Studio.
    if (empty($project['jobs_enabled'])) {
      $this->io->error('CI/CD is not enabled for this application in code studio. Enable it first and then try again.');
      return self::FAILURE;
    }

    try {
      $phpVersionAlreadySet = FALSE;
      // Get all variables of the project.
      $allProjectVariables = $this->gitLabClient->projects()->variables($project['id']);
      if (!empty($allProjectVariables)) {
        $variables = array_column($allProjectVariables, 'value', 'key');
        $phpVersionAlreadySet = $variables['PHP_VERSION'] ?? FALSE;
      }
      // If PHP version is not set in variables.
      if (!$phpVersionAlreadySet) {
        $this->gitLabClient->projects()->addVariable($project['id'], 'PHP_VERSION', $phpVersion);
      }
      else {
        // If variable already exists, updating the variable.
        $this->gitLabClient->projects()->updateVariable($project['id'], 'PHP_VERSION', $phpVersion);
      }
    }
    catch (RuntimeException) {
      $this->io->error("Unable to update the PHP version to $phpVersion");
      return self::FAILURE;
    }

    $this->io->success("PHP version is updated to $phpVersion successfully!");
    return self::SUCCESS;
  }

}
