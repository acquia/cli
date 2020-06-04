<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardDeleteSshKeyCommand.
 */
class IdeWizardDeleteSshKeyCommand extends IdeWizardCommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ide:wizard:ssh-key:delete')
      ->setDescription('Wizard to delete SSH key for IDE from Cloud')
      ->setHidden(!CommandBase::isAcquiaCloudIde());
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
    $this->requireCloudIdeEnvironment();

    $cloud_key = $this->findIdeSshKeyOnCloud();
    if (!$cloud_key) {
      throw new AcquiaCliException('Could not find an SSH key on Acquia Cloud matching any local key in this IDE.');
    }

    $this->deleteSshKeyFromCloud($cloud_key);
    $this->deleteLocalIdeSshKey();

    $this->output->writeln("<info>Deleted local files <comment>{$this->publicSshKeyFilepath}</comment> and <comment>{$this->privateSshKeyFilepath}</comment>");

    return 0;
  }

}
