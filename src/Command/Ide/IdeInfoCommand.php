<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Attribute\RequireAuth;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ide:info', description: 'Print information about a Cloud IDE')]
final class IdeInfoCommand extends IdeCommandBase {

  protected function configure(): void {
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $applicationUuid = $this->determineCloudApplication();

    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $idesResource = new Ides($acquiaCloudClient);

    $ide = $this->promptIdeChoice("Select an IDE to get more information:", $idesResource, $applicationUuid);
    $response = $idesResource->get($ide->uuid);
    $this->io->definitionList(
      ['IDE property' => 'IDE value'],
      new TableSeparator(),
      ['UUID' => $response->uuid],
      ['Label' => $response->label],
      ['Owner name' => $response->owner->first_name . ' ' . $response->owner->last_name],
      ['Owner username' => $response->owner->username],
      ['Owner email' => $response->owner->mail],
      ['Cloud application' => $response->links->application->href],
      ['IDE URL' => $response->links->ide->href],
      ['Web URL' => $response->links->web->href]
    );

    return Command::SUCCESS;
  }

}
