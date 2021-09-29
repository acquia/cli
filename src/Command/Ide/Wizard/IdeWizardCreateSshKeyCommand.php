<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;

/**
 * Class IdeWizardCreateSshKeyCommand.
 */
class IdeWizardCreateSshKeyCommand extends IdeWizardCommandBase {

  protected static $defaultName = 'ide:wizard:ssh-key:create-upload';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Wizard to perform first time setup tasks within an IDE')
      ->setAliases(['ide:wizard'])
      ->setHidden(!CommandBase::isAcquiaCloudIde());
  }

}
