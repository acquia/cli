<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'env:create', 'Create a new Continuous Delivery Environment (CDE)')]
class EnvCreateCommand extends CommandBase {

  private Checklist $checklist;

  protected function configure(): void {
    $this->addArgument('label', InputArgument::REQUIRED, 'The label of the new environment');
    $this->addArgument('branch', InputArgument::OPTIONAL, 'The vcs path (git branch name) to deploy to the new environment');
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->output = $output;
    $cloudAppUuid = $this->determineCloudApplication(TRUE);
    $label = $input->getArgument('label');
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $environmentsResource = new Environments($acquiaCloudClient);
    $this->checklist = new Checklist($output);

    $this->validateLabel($environmentsResource, $cloudAppUuid, $label);
    $branch = $this->getBranch($acquiaCloudClient, $cloudAppUuid, $input);
    $databaseNames = $this->getDatabaseNames($acquiaCloudClient, $cloudAppUuid);

    $this->checklist->addItem("Initiating environment creation");
    $response = $environmentsResource->create($cloudAppUuid, $label, $branch, $databaseNames);
    $notificationUuid = CommandBase::getNotificationUuidFromResponse($response);
    $this->checklist->completePreviousItem();

    $success = function () use ($environmentsResource, $cloudAppUuid, $label): void {
      $environments = $environmentsResource->getAll($cloudAppUuid);
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
    $this->waitForNotificationToComplete($acquiaCloudClient, $notificationUuid, "Waiting for the environment to be ready. This usually takes 2 - 15 minutes.", $success);

    return Command::SUCCESS;
  }

  private function validateLabel(Environments $environmentsResource, string $cloudAppUuid, string $title): void {
    $this->checklist->addItem("Checking to see that label is unique");
    /** @var \AcquiaCloudApi\Response\EnvironmentResponse[] $environments */
    $environments = $environmentsResource->getAll($cloudAppUuid);
    foreach ($environments as $environment) {
      if ($environment->label === $title) {
        throw new AcquiaCliException("An environment named $title already exists.");
      }
    }
    $this->checklist->completePreviousItem();
  }

  private function getBranch(Client $acquiaCloudClient, ?string $cloudAppUuid, InputInterface $input): string {
    $branchesAndTags = $acquiaCloudClient->request('get', "/applications/$cloudAppUuid/code");
    if ($input->getArgument('branch')) {
      $branch = $input->getArgument('branch');
      if (!in_array($branch, array_column($branchesAndTags, 'name'), TRUE)) {
        throw new AcquiaCliException("There is no branch or tag with the name $branch on the remote VCS.", );
      }
      return $branch;
    }
    $branchOrTag = $this->promptChooseFromObjectsOrArrays($branchesAndTags, 'name', 'name', "Choose a branch or tag to deploy to the new environment");
    return $branchOrTag->name;
  }

  /**
   * @return array<mixed>
   */
  private function getDatabaseNames(Client $acquiaCloudClient, ?string $cloudAppUuid): array {
    $this->checklist->addItem("Determining default database");
    $databasesResource = new Databases($acquiaCloudClient);
    $databases = $databasesResource->getNames($cloudAppUuid);
    $databaseNames = [];
    foreach ($databases as $database) {
      $databaseNames[] = $database->name;
    }
    $this->checklist->completePreviousItem();
    return $databaseNames;
  }

}
