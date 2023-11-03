<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'env:delete')]
class EnvDeleteCommand extends CommandBase {

  protected function configure(): void {
    $this->setDescription('Delete a Continuous Delivery Environment (CDE)');
    $this->acceptEnvironmentId();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->output = $output;
    $cloudAppUuid = $this->determineCloudApplication(TRUE);
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $environmentsResource = new Environments($acquiaCloudClient);
    $environment = $this->determineEnvironment($environmentsResource, $cloudAppUuid);
    $environmentsResource->delete($environment->uuid);

    $this->io->success([
      "The {$environment->label} environment is being deleted",
    ]);

    return Command::SUCCESS;
  }

  private function determineEnvironment(Environments $environmentsResource, string $cloudAppUuid): EnvironmentResponse {
    if ($this->input->getArgument('environmentId')) {
      // @todo Validate.
      $environmentId = $this->input->getArgument('environmentId');
      return $environmentsResource->get($environmentId);
    }
    $environments = $environmentsResource->getAll($cloudAppUuid);
    $cdes = [];
    foreach ($environments as $environment) {
      if ($environment->flags->cde) {
        $cdes[] = $environment;
      }
    }
    if (!$cdes) {
      throw new AcquiaCliException('There are no existing CDEs for Application ' . $cloudAppUuid);
    }
    return $this->promptChooseFromObjectsOrArrays($cdes, 'uuid', 'label', "Which Continuous Delivery Environment (CDE) do you want to delete?");
  }

}
