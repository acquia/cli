<?php

namespace Acquia\Cli\Command\Ide;

use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateProjectCommand.
 *
 * @package Grasmash\YamlCli\Command
 */
class IdeDeleteCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Delete a Cloud IDE')
    ->addOption('cloud-app-uuid', 'uuid', InputOption::VALUE_REQUIRED, 'The UUID of the associated Acquia Cloud Application');
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
    $response = $ides_resource->delete($ide->uuid);
    $this->output->writeln($response->message);

    return 0;
  }

}
