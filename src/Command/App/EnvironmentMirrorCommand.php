<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\OperationResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EnvironmentMirrorCommand.
 */
class EnvironmentMirrorCommand extends CommandBase {

  protected static $defaultName = 'app:environment:mirror';

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private Checklist $checklist;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Makes one environment identical to another in terms of code, database, files, and configuration.');
    $this->addArgument('source-environment', InputArgument::REQUIRED, 'The Cloud Platform source environment ID or alias')
      ->addUsage(self::getDefaultName() . ' [<environmentAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp.dev')
      ->addUsage(self::getDefaultName() . ' 12345-abcd1234-1111-2222-3333-0e02b2c3d470');
    $this->addArgument('destination-environment', InputArgument::REQUIRED, 'The Cloud Platform destination environment ID or alias')
      ->addUsage(self::getDefaultName() . ' [<environmentAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp.dev')
      ->addUsage(self::getDefaultName() . ' 12345-abcd1234-1111-2222-3333-0e02b2c3d470');
    $this->addOption('no-code', 'c');
    $this->addOption('no-databases', 'd');
    $this->addOption('no-files', 'f');
    $this->addOption('no-config', 'p');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->checklist = new Checklist($output);
    $output_callback = $this->getOutputCallback($output, $this->checklist);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environments_resource = new Environments($acquia_cloud_client);
    $source_environment_uuid = $input->getArgument('source-environment');
    $destination_environment_uuid = $input->getArgument('destination-environment');

    $this->checklist->addItem("Fetching information about source environment");
    $source_environment = $environments_resource->get($source_environment_uuid);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem("Fetching information about destination environment");
    $destination_environment = $environments_resource->get($destination_environment_uuid);
    $this->checklist->completePreviousItem();

    $answer = $this->io->confirm("Are you sure that you want to overwrite everything on {$destination_environment->label} ({$destination_environment->name}) and replace it with source data from {$source_environment->label} ({$source_environment->name})");
    if (!$answer) {
      return 1;
    }

    if (!$input->getOption('no-code')) {
      $code_copy_response = $this->mirrorCode($acquia_cloud_client, $destination_environment_uuid, $source_environment, $output_callback);
    }

    if (!$input->getOption('no-databases')) {
      $db_copy_response = $this->mirrorDatabase($acquia_cloud_client, $source_environment_uuid, $destination_environment_uuid, $output_callback);
    }

    if (!$input->getOption('no-files')) {
      $files_copy_response = $this->mirrorFiles($environments_resource, $source_environment_uuid, $destination_environment_uuid, $output_callback);
    }

    if (!$input->getOption('no-config')) {
      $config_copy_response = $this->mirrorConfig($source_environment, $destination_environment, $environments_resource, $destination_environment_uuid, $output_callback);
    }

    if (isset($code_copy_response)) {
      $this->waitForNotificationSuccess($acquia_cloud_client, $this->getNotificationUuidFromResponse($code_copy_response), 'Waiting for code copy to complete');
    }
    if (isset($db_copy_response)) {
      $this->waitForNotificationSuccess($acquia_cloud_client, $this->getNotificationUuidFromResponse($db_copy_response), 'Waiting for database copy to complete');
    }
    if (isset($files_copy_response)) {
      $this->waitForNotificationSuccess($acquia_cloud_client, $this->getNotificationUuidFromResponse($files_copy_response), 'Waiting for files copy to complete');
    }
    if (isset($config_copy_response)) {
      $this->waitForNotificationSuccess($acquia_cloud_client, $this->getNotificationUuidFromResponse($config_copy_response), 'Waiting for config copy to complete');
    }

    $this->io->success([
      "Done! {$destination_environment->label} now matches {$source_environment->label}",
      "You can visit it here:",
      "https://" . $destination_environment->domains[0],
    ]);

    return 0;
  }

  /**
   * @param \stdClass[] $databases
   *
   * @return object|null
   */
  protected function getDefaultDatabase(array $databases): ?object {
    foreach ($databases as $database) {
      if ($database->flags->default) {
        return $database;
      }
    }
    return NULL;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param mixed $source_environment_uuid
   * @param mixed $destination_environment_uuid
   * @param callable $output_callback
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  protected function mirrorDatabase(Client $acquia_cloud_client, mixed $source_environment_uuid, mixed $destination_environment_uuid, callable $output_callback): OperationResponse {
    $this->checklist->addItem("Initiating database copy");
    $output_callback('out', "Getting a list of databases");
    $databases_resource = new Databases($acquia_cloud_client);
    $databases = $acquia_cloud_client->request('get', "/environments/$source_environment_uuid/databases");
    $default_database = $this->getDefaultDatabase($databases);
    $output_callback('out', "Copying {$default_database->name}");

    // @todo Create database if its missing.
    $db_copy_response = $databases_resource->copy($source_environment_uuid, $default_database->name, $destination_environment_uuid);
    $this->checklist->completePreviousItem();
    return $db_copy_response;
  }

  /**
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   * @param mixed $destination_environment_uuid
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $source_environment
   * @param callable $output_callback
   *
   * @return mixed
   */
  protected function mirrorCode(Client $acquia_cloud_client, mixed $destination_environment_uuid, EnvironmentResponse $source_environment, callable $output_callback): mixed {
    $this->checklist->addItem("Initiating code switch");
    $output_callback('out', "Switching to {$source_environment->vcs->path}");
    $code_copy_response = $acquia_cloud_client->request('post', "/environments/$destination_environment_uuid/code/actions/switch", [
      'form_params' => [
        'branch' => $source_environment->vcs->path,
      ],
    ]);
    $this->checklist->completePreviousItem();
    return $code_copy_response;
  }

  /**
   * @param \AcquiaCloudApi\Endpoints\Environments $environments_resource
   * @param mixed $source_environment_uuid
   * @param mixed $destination_environment_uuid
   * @param callable $output_callback
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  protected function mirrorFiles(Environments $environments_resource, mixed $source_environment_uuid, mixed $destination_environment_uuid, callable $output_callback): OperationResponse {
    $this->checklist->addItem("Initiating files copy");
    $files_copy_response = $environments_resource->copyFiles($source_environment_uuid, $destination_environment_uuid);
    $this->checklist->completePreviousItem();
    return $files_copy_response;
  }

  /**
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $source_environment
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $destination_environment
   * @param \AcquiaCloudApi\Endpoints\Environments $environments_resource
   * @param mixed $destination_environment_uuid
   * @param callable $output_callback
   *
   * @return \AcquiaCloudApi\Response\OperationResponse
   */
  protected function mirrorConfig(EnvironmentResponse $source_environment, EnvironmentResponse $destination_environment, Environments $environments_resource, mixed $destination_environment_uuid, callable $output_callback): OperationResponse {
    $this->checklist->addItem("Initiating config copy");
    $output_callback('out', "Copying PHP version, acpu memory limit, etc.");
    $config = (array) $source_environment->configuration->php;
    $config['apcu'] = max(32, $source_environment->configuration->php->apcu);
    if ($config['version'] == $destination_environment->configuration->php->version) {
      unset($config['version']);
    }
    unset($config['memcached_limit']);
    $config_copy_response = $environments_resource->update($destination_environment_uuid, $config);
    $this->checklist->completePreviousItem();
    return $config_copy_response;
  }

}
