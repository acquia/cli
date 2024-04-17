<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ide:delete', description: 'Delete a Cloud IDE')]
final class IdeDeleteCommand extends IdeCommandBase {

  use SshCommandTrait;

  protected function configure(): void {
    $this->acceptApplicationUuid();
    // @todo make this an argument
    $this->addOption('uuid', NULL, InputOption::VALUE_OPTIONAL, 'UUID of the IDE to delete');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $idesResource = new Ides($acquiaCloudClient);

    $ideUuid = $input->getOption('uuid');
    if ($ideUuid) {
      $ide = $idesResource->get($ideUuid);
    }
    else {
      $cloudApplicationUuid = $this->determineCloudApplication();
      $ide = $this->promptIdeChoice("Select the IDE you'd like to delete:", $idesResource, $cloudApplicationUuid);
      $answer = $this->io->confirm("Are you sure you want to delete <options=bold>$ide->label</>");
      if (!$answer) {
        $this->io->writeln('Ok, never mind.');
        return Command::FAILURE;
      }
    }
    $response = $idesResource->delete($ide->uuid);
    $this->io->writeln($response->message);

    // Check to see if an SSH key for this IDE exists on Cloud.
    $cloudKey = $this->findIdeSshKeyOnCloud($ide->label, $ide->uuid);
    if ($cloudKey) {
      $answer = $this->io->confirm('Would you like to delete the SSH key associated with this IDE from your Cloud Platform account?');
      if ($answer) {
        $this->deleteSshKeyFromCloud($output, $cloudKey);
      }
    }

    return Command::SUCCESS;
  }

}
