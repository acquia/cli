<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ide:wizard:ssh-key:create-upload', description: 'Wizard to perform first time setup tasks within an IDE', aliases: ['ide:wizard'])]
final class IdeWizardCreateSshKeyCommand extends IdeWizardCommandBase
{
    protected function configure(): void
    {
        $this
        ->setHidden(!CommandBase::isAcquiaCloudIde());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checklist = new Checklist($output);

        // Get Cloud account.
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $accountAdapter = new Account($acquiaCloudClient);
        $account = $accountAdapter->get();
        $this->validateRequiredCloudPermissions(
            $acquiaCloudClient,
            self::getThisCloudIdeCloudAppUuid(),
            $account,
            [
            // Add SSH key to git repository.
            "add ssh key to git",
            // Add SSH key to non-production environments.
            "add ssh key to non-prod",
            ]
        );

        $keyWasUploaded = false;
        // Create local SSH key.
        if (!$this->localSshKeyExists() || !$this->passPhraseFileExists()) {
            // Just in case the public key exists and the private doesn't, remove the public key.
            $this->deleteLocalSshKey();
            // Just in case there's an orphaned key on the Cloud Platform for this Cloud IDE.
            $this->deleteThisSshKeyFromCloud($output);

            $checklist->addItem('Creating a local SSH key');

            // Create SSH key.
            $password = md5(random_bytes(10));
            $this->savePassPhraseToFile($password);
            $this->createSshKey($this->privateSshKeyFilename, $password);

            $checklist->completePreviousItem();
            $keyWasUploaded = true;
        } else {
            $checklist->addItem('Already created a local key');
            $checklist->completePreviousItem();
        }

        // Upload SSH key to the Cloud Platform.
        if (!$this->userHasUploadedThisKeyToCloud($this->getSshKeyLabel())) {
            $checklist->addItem('Uploading the local key to the Cloud Platform');

            // Just in case there is an uploaded key,  but it doesn't actually match
            // the local key, delete remote key!
            $this->deleteThisSshKeyFromCloud($output);
            $publicKey = $this->localMachineHelper->readFile($this->publicSshKeyFilepath);
            $this->uploadSshKey($this->getSshKeyLabel(), $publicKey);

            $checklist->completePreviousItem();
            $keyWasUploaded = true;
        } else {
            $checklist->addItem('Already uploaded the local key to the Cloud Platform');
            $checklist->completePreviousItem();
        }

        // Add SSH key to local keychain.
        if (!$this->sshKeyIsAddedToKeychain()) {
            $checklist->addItem('Adding the SSH key to local keychain');
            $this->addSshKeyToAgent($this->publicSshKeyFilepath, $this->getPassPhraseFromFile());
        } else {
            $checklist->addItem('Already added the SSH key to local keychain');
        }
        $checklist->completePreviousItem();

        // Wait for the key to register on the Cloud Platform.
        if ($keyWasUploaded) {
            if ($this->input->isInteractive() && !$this->promptWaitForSsh($this->io)) {
                $this->io->success('Your SSH key has been successfully uploaded to the Cloud Platform.');
                return Command::SUCCESS;
            }
            $this->pollAcquiaCloudUntilSshSuccess($output);
        }

        return Command::SUCCESS;
    }
}
