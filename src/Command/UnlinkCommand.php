<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UnlinkCommand.
 */
class UnlinkCommand extends CommandBase {

  protected static $defaultName = 'unlink';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Remove local association between your project and an Acquia Cloud application');
  }

  /**
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->validateCwdIsValidDrupalProject();

    $repo_root = $this->repoRoot;
    $local_user_config = $this->acliDatastore->get($this->acliConfigFilename);
    if (!$this->getAppUuidFromLocalProjectInfo()) {
      throw new AcquiaCliException('There is no Acquia Cloud application linked to {repo_root}', ['repo_root' => $repo_root]);
    }
    foreach ($local_user_config['localProjects'] as $key => $project) {
      if ($project['directory'] === $repo_root) {
        // @todo Add confirmation.
        unset($local_user_config['localProjects'][$key]);
        $this->localProjectInfo = NULL;
        $this->acliDatastore->set($this->acliConfigFilename, $local_user_config);

        $application = $this->getCloudApplication($project['cloud_application_uuid']);
        $output->writeln("<info>Unlinked <options=bold>$repo_root</> from Cloud application <options=bold>{$application->name}</></info>");
        return 0;
      }
    }

    throw new AcquiaCliException('This project is not linked to a Cloud application.');
  }

}
