<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:logout', description: 'Remove Cloud Platform API credentials', aliases: ['logout'])]
final class AuthLogoutCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->addOption('delete', null, InputOption::VALUE_NEGATABLE, 'Delete the active Cloud Platform API credentials');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $activeKey = $this->datastoreCloud->get('acli_key');
        $deviceToken = $this->datastoreCloud->get('device_token');

        if (!$activeKey && !$deviceToken) {
            throw new AcquiaCliException('There are no active Cloud Platform credentials');
        }

        // Remove device token session if present.
        if ($deviceToken) {
            $this->datastoreCloud->remove('device_token');
            $output->writeln('<info>Device code session removed.</info>');
        }

        // Remove legacy API key if active.
        if ($activeKey) {
            $keys = $this->datastoreCloud->get('keys');
            $activeKeyLabel = $keys[$activeKey]['label'];
            $output->writeln("<info>The key <options=bold>$activeKeyLabel</> will be deactivated on this machine. However, the credentials will remain on disk and can be reactivated by running <options=bold>acli auth:login</> unless you also choose to delete them.</info>");
            $delete = $this->determineOption('delete', false, null, null, false);
            $this->datastoreCloud->remove('acli_key');
            $action = 'deactivated';
            if ($delete) {
                $this->datastoreCloud->remove("keys.$activeKey");
                $action = 'deleted';
            }
            $output->writeln("<info>The active Cloud Platform API credentials were $action</info>");
        }
        $output->writeln('<info>No Cloud Platform credentials are active. Run <options=bold>acli auth:login</> to authenticate.</info>');

        return Command::SUCCESS;
    }
}
