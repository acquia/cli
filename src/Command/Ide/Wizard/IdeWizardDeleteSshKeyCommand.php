<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshCommandTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ide:wizard:ssh-key:delete', description: 'Wizard to delete SSH key for IDE from Cloud (Added in 1.0.0).')]
final class IdeWizardDeleteSshKeyCommand extends IdeWizardCommandBase
{
    use SshCommandTrait;

    protected function configure(): void
    {
        $this
            ->setHidden(!CommandBase::isAcquiaCloudIde());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->requireCloudIdeEnvironment();

        $cloudKey = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeLabel(), $this::getThisCloudIdeUuid());
        if (!$cloudKey) {
            throw new AcquiaCliException('Could not find an SSH key on the Cloud Platform matching any local key in this IDE.');
        }

        $this->deleteSshKeyFromCloud($output, $cloudKey);
        $this->deleteLocalSshKey();

        $this->output->writeln("<info>Deleted local files <options=bold>$this->publicSshKeyFilepath</> and <options=bold>$this->privateSshKeyFilepath</>");

        return Command::SUCCESS;
    }
}
