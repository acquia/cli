<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardDeleteSshKeyCommand.
 */
class IdeWizardDeleteSshKeyCommand extends IdeWizardCommandBase {

  use SshCommandTrait;

  protected static $defaultName = 'ide:wizard:ssh-key:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Wizard to delete SSH key for IDE from Cloud')
      ->setHidden(!CommandBase::isAcquiaCloudIde());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();

    $cloud_key = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeUuid());
    if (!$cloud_key) {
      throw new AcquiaCliException('Could not find an SSH key on the Cloud Platform matching any local key in this IDE.');
    }

    $this->deleteSshKeyFromCloud($output, $cloud_key);
    $this->deleteLocalSshKey();

    $this->output->writeln("<info>Deleted local files <options=bold>{$this->publicSshKeyFilepath}</> and <options=bold>{$this->privateSshKeyFilepath}</>");

    return 0;
  }

}
