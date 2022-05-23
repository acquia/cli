<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DeleteCdeCommand.
 */
class DeleteCdeCommand extends CommandBase {

  protected static $defaultName = 'app:environment:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Delete a Continuous Delivery Environment (CDE)');
    $this->acceptEnvironmentId();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->output = $output;
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environments_resource = new Environments($acquia_cloud_client);
    $environments = $environments_resource->getAll($cloud_app_uuid);
    $cdes = [];
    foreach ($environments as $environment) {
      if ($environment->flags->cde) {
        $cdes[] = $environment;
      }
    }
    $environment = $this->promptChooseFromObjectsOrArrays($cdes, 'uuid', 'label', "Which Continuous Delivery Environment (CDE) do you want to delete?");
    $environments_resource->delete($environment->uuid);

    $this->io->success([
      "The {$environment->label} is being deleted",
    ]);

    return 0;
  }

}
