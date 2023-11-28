<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ide:delete')]
class IdeDeleteCommand extends IdeCommandBase {

  /**
   * @var string
   */
  // phpcs:ignore
  protected static $defaultDescription = 'Delete a Cloud IDE';
  use SshCommandTrait;

  protected function configure(): void {
    $this->acceptApplicationUuid();
    // @todo Add option to accept an ide UUID.
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $idesResource = new Ides($acquiaCloudClient);

    $cloudApplicationUuid = $this->determineCloudApplication();
    $ide = $this->promptIdeChoice("Select the IDE you'd like to delete:", $idesResource, $cloudApplicationUuid);
    $answer = $this->io->confirm("Are you sure you want to delete <options=bold>{$ide->label}</>");
    if (!$answer) {
      $this->io->writeln('Ok, nevermind.');
      return 1;
    }
    $response = $idesResource->delete($ide->uuid);
    $this->io->writeln($response->message);
    // @todo Remove after CXAPI-8261 is closed.
    $this->io->writeln("This process usually takes a few minutes.");

    // Check to see if an SSH key for this IDE exists on Cloud.
    $cloudKey = $this->findIdeSshKeyOnCloud($ide->uuid);
    if ($cloudKey) {
      $answer = $this->io->confirm('Would you like to delete the SSH key associated with this IDE from your Cloud Platform account?');
      if ($answer) {
        $this->deleteSshKeyFromCloud($output, $cloudKey);
      }
    }

    return Command::SUCCESS;
  }

}
