<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use React\EventLoop\Loop;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateCdeCommand.
 */
class CreateCdeCommand extends CommandBase {

  protected static $defaultName = 'app:environment:create';

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private Checklist $checklist;

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Create a new Continuous Delivery Environment (CDE)');
    $this->addArgument('title', InputArgument::REQUIRED, 'The title of the new environment');
    $this->addArgument('branch', InputArgument::OPTIONAL, 'The vcs path (git branch name) to deploy to the new environment');
    $this->acceptApplicationUuid();
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
    $title = $input->getArgument('title');
    $acquia_cloud_client = $this->cloudApiClientService->getClient();

    $this->checklist = new Checklist($output);
    $this->checklist->addItem("Checking to see that label is unique");
    $environments_resource = new Environments($acquia_cloud_client);
    /** @var \AcquiaCloudApi\Response\EnvironmentResponse[] $environments */
    $environments = $environments_resource->getAll($cloud_app_uuid);
    foreach ($environments as $environment) {
      if ($environment->label == $title) {
        $this->io->error([
          "An environment named $title already exists.",
        ]);
        return 1;
      }
    }
    $this->checklist->completePreviousItem();

    $branches_and_tags = $acquia_cloud_client->request('get', "/applications/$cloud_app_uuid/code");
    if ($input->getArgument('branch')) {
      $branch = $input->getArgument('branch');
      $branch_names = [];
      foreach ($branches_and_tags as $branches_or_tag) {
        $branch_names[] = $branches_or_tag->name;
      }
      if (!array_search($branch, $branch_names)) {
        $this->io->error([
          "There is no branch or tag with the name $branch on the remote VCS.",
        ]);
        return 1;
      }
    }
    else {
      $branch = $this->promptChooseFromObjectsOrArrays($branches_and_tags, 'name', 'name', "Choose a branch or tag to deploy to the new environment");
    }

    $this->checklist->addItem("Determining default database");
    $databases_resource = new Databases($acquia_cloud_client);
    $databases = $databases_resource->getAll($cloud_app_uuid);
    $database_names = [];
    foreach ($databases as $database) {
      $database_names[] = $database->name;
    }
    $this->checklist->completePreviousItem();

    $this->checklist->addItem("Creating environment");
    $response = $environments_resource->create($cloud_app_uuid, $title, $branch, $database_names);

    $notification_url = $response->links->notification->href;
    $url_parts = explode('/', $notification_url);
    $notification_uuid = $url_parts[5];

    $cde_url = str_replace('api/', 'a/', $response->links->self->href);
    $url_parts = explode('/', $cde_url);
    $cde_uuid = $url_parts[5];

    $success = $this->waitForNotificationSuccess($acquia_cloud_client, $notification_uuid, "Waiting for the environment to be ready. This usually takes 2 - 15 minutes.");
    if ($success) {
      $this->checklist->completePreviousItem();
      $this->output->writeln('');
      $this->output->writeln("<comment>Your CDE URL:</comment> <href=$cde_url>$cde_url</>");
      return 0;
    }

    return 1;
  }

}