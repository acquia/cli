<?php

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EnvDeleteCommand.
 */
class EnvDeleteCommand extends CommandBase {

  protected static $defaultName = 'env:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Delete a Continuous Delivery Environment (CDE)');
    $this->acceptEnvironmentId();
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->output = $output;
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environments_resource = new Environments($acquia_cloud_client);
    $environment = $this->determineEnvironment($environments_resource, $cloud_app_uuid);
    $environments_resource->delete($environment->uuid);

    $this->io->success([
      "The {$environment->label} environment is being deleted",
    ]);

    return 0;
  }

  private function determineEnvironment(Environments $environments_resource, string $cloud_app_uuid): EnvironmentResponse {
    if ($this->input->getArgument('environmentId')) {
      // @todo Validate.
      $environment_id = $this->input->getArgument('environmentId');
      return $environments_resource->get($environment_id);
    }
    $environments = $environments_resource->getAll($cloud_app_uuid);
    $cdes = [];
    foreach ($environments as $environment) {
      if ($environment->flags->cde) {
        $cdes[] = $environment;
      }
    }
    if (!$cdes) {
      throw new AcquiaCliException('There are no existing CDEs for Application ' . $cloud_app_uuid);
    }
    return $this->promptChooseFromObjectsOrArrays($cdes, 'uuid', 'label', "Which Continuous Delivery Environment (CDE) do you want to delete?");
  }

}
