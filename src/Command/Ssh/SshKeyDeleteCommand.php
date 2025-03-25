<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Helpers\SshCommandTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ssh-key:delete', description: 'Delete an SSH key')]
final class SshKeyDeleteCommand extends SshKeyCommandBase
{
    use SshCommandTrait;

    protected function configure(): void
    {
        $this
            ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->deleteSshKeyFromCloud($output, $input->getOption('cloud-key-uuid'));
    }
}
