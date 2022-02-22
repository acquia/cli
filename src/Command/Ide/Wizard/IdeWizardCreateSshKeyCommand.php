<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCreateSshKeyCommand.
 */
class IdeWizardCreateSshKeyCommand extends IdeWizardCommandBase {

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private $checklist;

  protected static $defaultName = 'ide:wizard:ssh-key:create-upload';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Wizard to perform first time setup tasks within an IDE')
      ->setAliases(['ide:wizard'])
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
    $this->checklist = new Checklist($output);
    $key_was_uploaded = FALSE;

    // Create local SSH key.
    if (!$this->localSshKeyExists() || !$this->passPhraseFileExists()) {
      // Just in case the public key exists and the private doesn't, remove the public key.
      $this->deleteLocalSshKey();
      // Just in case there's an orphaned key on the Cloud Platform for this Cloud IDE.
      $this->deleteThisSshKeyFromCloud();

      $this->checklist->addItem('Creating a local SSH key');

      // Create SSH key.
      $password = md5(random_bytes(10));
      $this->savePassPhraseToFile($password);
      $this->createSshKey($this->privateSshKeyFilename, $password);

      $this->checklist->completePreviousItem();
      $key_was_uploaded = TRUE;
    }
    else {
      $this->checklist->addItem('Already created a local key');
      $this->checklist->completePreviousItem();
    }

    // Upload SSH key to the Cloud Platform.
    if (!$this->userHasUploadedThisKeyToCloud($this->getSshKeyLabel())) {
      $this->checklist->addItem('Uploading the local key to the Cloud Platform');

      // Just in case there is an uploaded key,  but it doesn't actually match
      // the local key, delete remote key!
      $this->deleteThisSshKeyFromCloud();
      $public_key = $this->localMachineHelper->readFile($this->publicSshKeyFilepath);
      $chosen_local_key = basename($this->publicSshKeyFilepath);
      $this->uploadSshKey($this->getSshKeyLabel(), $chosen_local_key, $public_key);

      $this->checklist->completePreviousItem();
      $key_was_uploaded = TRUE;
    }
    else {
      $this->checklist->addItem('Already uploaded the local key to the Cloud Platform');
      $this->checklist->completePreviousItem();
    }

    // Add SSH key to local keychain.
    if (!$this->sshKeyIsAddedToKeychain()) {
      $this->checklist->addItem('Adding the SSH key to local keychain');
      $this->addSshKeyToAgent($this->publicSshKeyFilepath, $this->getPassPhraseFromFile());
    }
    else {
      $this->checklist->addItem('Already added the SSH key to local keychain');
    }
    $this->checklist->completePreviousItem();

    // Wait for the key to register on the Cloud Platform.
    if ($key_was_uploaded) {
      $this->pollAcquiaCloudUntilSshSuccess($output);
    }

    return 0;
  }

}
