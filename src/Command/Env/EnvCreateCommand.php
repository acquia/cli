<?php

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvCreateCommand extends CommandBase {

  protected static $defaultName = 'env:create';

  private Checklist $checklist;

  protected function configure(): void {
    $this->setDescription('Create a new Continuous Delivery Environment (CDE)');
    $this->addArgument('label', InputArgument::REQUIRED, 'The label of the new environment');
    $this->addArgument('branch', InputArgument::OPTIONAL, 'The vcs path (git branch name) to deploy to the new environment');
    $this->acceptApplicationUuid();
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->output = $output;
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);
    $label = $input->getArgument('label');
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environments_resource = new Environments($acquia_cloud_client);
    $this->checklist = new Checklist($output);

    $this->validateLabel($environments_resource, $cloud_app_uuid, $label);
    $branch = $this->getBranch($acquia_cloud_client, $cloud_app_uuid, $input);
    $database_names = $this->getDatabaseNames($acquia_cloud_client, $cloud_app_uuid);

    $this->checklist->addItem("Initiating environment creation");
    $response = $environments_resource->create($cloud_app_uuid, $label, $branch, $database_names);
    $notification_uuid = $this->getNotificationUuidFromResponse($response);
    $this->checklist->completePreviousItem();

    $success = function () use ($environments_resource, $cloud_app_uuid, $label): void {
      $environments = $environments_resource->getAll($cloud_app_uuid);
      foreach ($environments as $environment) {
        if ($environment->label === $label) {
          break;
        }
      }
      if (isset($environment)) {
        $this->output->writeln([
          '',
          "<comment>Your CDE URL:</comment> <href=https://{$environment->domains[0]}>{$environment->domains[0]}</>",
        ]);
      }
    };
    $this->waitForNotificationToComplete($acquia_cloud_client, $notification_uuid, "Waiting for the environment to be ready. This usually takes 2 - 15 minutes.", $success);

    return 0;
  }

  private function validateLabel(Environments $environments_resource, string $cloud_app_uuid, string $title): void {
    $this->checklist->addItem("Checking to see that label is unique");
    /** @var \AcquiaCloudApi\Response\EnvironmentResponse[] $environments */
    $environments = $environments_resource->getAll($cloud_app_uuid);
    foreach ($environments as $environment) {
      if ($environment->label === $title) {
        throw new AcquiaCliException("An environment named $title already exists.");
      }
    }
    $this->checklist->completePreviousItem();
  }

  private function getBranch(Client $acquia_cloud_client, ?string $cloud_app_uuid, InputInterface $input): string {
    $branches_and_tags = $acquia_cloud_client->request('get', "/applications/$cloud_app_uuid/code");
    if ($input->getArgument('branch')) {
      $branch = $input->getArgument('branch');
      if (!in_array($branch, array_column($branches_and_tags, 'name'), TRUE)) {
        throw new AcquiaCliException("There is no branch or tag with the name $branch on the remote VCS.", );
      }
      return $branch;
    }
    return $this->promptChooseFromObjectsOrArrays($branches_and_tags, 'name', 'name', "Choose a branch or tag to deploy to the new environment")->name;
  }

  /**
   * @return array
   */
  private function getDatabaseNames(Client $acquia_cloud_client, ?string $cloud_app_uuid): array {
    $this->checklist->addItem("Determining default database");
    $databases_resource = new Databases($acquia_cloud_client);
    $databases = $databases_resource->getNames($cloud_app_uuid);
    $database_names = [];
    foreach ($databases as $database) {
      $database_names[] = $database->name;
    }
    $this->checklist->completePreviousItem();
    return $database_names;
  }

}
