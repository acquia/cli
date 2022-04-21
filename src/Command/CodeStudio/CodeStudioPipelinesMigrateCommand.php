<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Response\ApplicationResponse;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Builder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Class CodeStudioPipelinesMigrateCommand.
 */
class CodeStudioPipelinesMigrateCommand extends CommandBase {

  protected static $defaultName = 'codestudio:pipelines-migrate';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('@todo')
      ->setAliases(['cs:pipelines']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    if (!$this->repoRoot) {
      $this->io->error(
        ['You current working directory does not appear to be a Drupal repository!'],
      );
      return 1;
    }
    $pipelines_filepath = Path::join($this->repoRoot, '.acquia-pipelines.yml');
    if ($this->localMachineHelper->getFilesystem()->exists($pipelines_filepath)) {
      $pipelines_config = Yaml::parseFile($pipelines_filepath);

      $gitlab_ci_config = [];
      // @todo Read $pipelines_config, add stuff to $gitlab_ci_config.

      $gitlab_ci_filepath = Path::join($this->repoRoot, '.gitlab-ci.yml');
      $this->localMachineHelper->getFilesystem()->dumpFile($gitlab_ci_filepath, Yaml::dump($gitlab_ci_config));
      $this->localMachineHelper->getFilesystem()->remove($pipelines_filepath);
      $this->io->success([
        "Created $gitlab_ci_filepath",
        "Removed $pipelines_filepath",
      ]);
    }
    else {
      $this->io->error(
        ['Could not find .acquia-pipelines.yml file in ' . $this->repoRoot],
      );
    }

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

}
