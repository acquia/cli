<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateProjectCommand.
 *
 * @package Grasmash\YamlCli\Command
 */
class IdeDeleteCommand extends IdeCommandBase {

  use SshCommandTrait;

  protected static $defaultName = 'ide:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Delete a Cloud IDE');
    $this->acceptApplicationUuid();
    // @todo Add option to accept an ide UUID.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);

    $cloud_application_uuid = $this->determineCloudApplication();
    $ide = $this->promptIdeChoice("Please select the IDE you'd like to delete:", $ides_resource, $cloud_application_uuid);
    $answer = $this->io->confirm("Are you sure you want to delete <options=bold>{$ide->label}</>");
    if (!$answer) {
      $this->io->writeln('Ok, nevermind.');
      return 1;
    }
    $response = $ides_resource->delete($ide->uuid);
    $this->io->writeln($response->message);
    // @todo Remove after CXAPI-8261 is closed.
    $this->io->writeln("This process usually takes a few minutes.");

    // Check to see if an SSH key for this IDE exists on Cloud.
    $cloud_key = $this->findIdeSshKeyOnCloud($ide->uuid);
    if ($cloud_key) {
      $answer = $this->io->confirm('Would you like to delete the SSH key associated with this IDE from your Cloud Platform account?');
      if ($answer) {
        $this->deleteSshKeyFromCloud($output, $cloud_key);
      }
    }

    return 0;
  }

}
