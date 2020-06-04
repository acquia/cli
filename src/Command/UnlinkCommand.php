<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UnlinkCommand.
 */
class UnlinkCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('unlink')
      ->setDescription('Remove local association between your project and an Acquia Cloud application');
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

    $repo_root = $this->getApplication()->getContainer()->getParameter('repo_root');
    $local_user_config = $this->getApplication()->getContainer()->get('acli_datastore')->get($this->getApplication()->getContainer()->getParameter('acli_config.filename'));
    if (!$this->getAppUuidFromLocalProjectInfo()) {
      throw new AcquiaCliException('There is no Acquia Cloud application linked to {repo_root}', ['repo_root' => $repo_root]);
    }
    foreach ($local_user_config['localProjects'] as $key => $project) {
      if ($project['directory'] === $repo_root) {
        // @todo Add confirmation.
        unset($local_user_config['localProjects'][$key]);
        $this->localProjectInfo = NULL;
        $this->getApplication()->getContainer()->get('acli_datastore')->set($this->getApplication()->getContainer()->getParameter('acli_config.filename'), $local_user_config);
        $application = $this->getCloudApplication($project['cloud_application_uuid']);
        $output->writeln("<info>Unlinked <comment>$repo_root</comment> from Cloud application <comment>{$application->name}</comment></info>");
        return 0;
      }
    }

    throw new AcquiaCliException('This project is not linked to a Cloud application.');
  }

}
