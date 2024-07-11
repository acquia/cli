<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ide:open', description: 'Open a Cloud IDE in your browser')]
final class IdeOpenCommand extends IdeCommandBase
{
    protected function configure(): void
    {
        $this
            ->setHidden(AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
        $this->acceptApplicationUuid();
        // @todo Add option to accept an ide UUID.
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $cloudApplicationUuid = $this->determineCloudApplication();
        $idesResource = new Ides($acquiaCloudClient);
        $ide = $this->promptIdeChoice("Select the IDE you'd like to open:", $idesResource, $cloudApplicationUuid);

        $this->output->writeln('');
        $this->output->writeln("<comment>Your IDE URL:</comment> <href={$ide->links->ide->href}>{$ide->links->ide->href}</>");
        $this->output->writeln("<comment>Your Drupal Site URL:</comment> <href={$ide->links->web->href}>{$ide->links->web->href}</>");
        $this->output->writeln('Opening your IDE in browser...');

        $this->localMachineHelper->startBrowser($ide->links->ide->href);

        return Command::SUCCESS;
    }
}
