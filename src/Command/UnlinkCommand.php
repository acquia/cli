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
    $this->setDescription('Remove local association between your project and a Cloud Platform application');
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->validateCwdIsValidDrupalProject();

    $repo_root = $this->repoRoot;
    if (!$this->getAppUuidFromLocalAcliConfig()) {
      throw new AcquiaCliException('There is no Cloud Platform application linked to {repo_root}', ['repo_root' => $repo_root]);
    }

    $application = $this->getCloudApplication($this->datastoreAcli->get('cloud_app_uuid'));
    $this->datastoreAcli->set('cloud_app_uuid', NULL);
    $output->writeln("<info>Unlinked <options=bold>$repo_root</> from Cloud application <options=bold>{$application->name}</></info>");

    return 0;
  }

}
