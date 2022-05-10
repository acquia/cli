<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MirrorEnvironmentCommand.
 */
class MirrorEnvironmentCommand extends CommandBase {

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
    $this->addArgument('source-environment', InputArgument::OPTIONAL, 'The Cloud Platform source environment ID or alias')
      ->addUsage(self::getDefaultName() . ' [<environmentAlias>]')
      ->addUsage(self::getDefaultName() . ' myapp.dev')
      ->addUsage(self::getDefaultName() . ' 12345-abcd1234-1111-2222-3333-0e02b2c3d470');
    $this->addArgument('destination-environment', InputArgument::OPTIONAL, 'The Cloud Platform destination environment ID or alias')
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
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environments_resource = new Environments($acquia_cloud_client);

    $this->checklist->addItem("Fetching information about source environment");
    $source_environment_uuid = $input->getArgument('source-environment');
    $source_environment = $environments_resource->get($source_environment_uuid);
    $this->checklist->completePreviousItem();

    $this->checklist->addItem("Fetching information about destination environment");
    $destination_environment_uuid = $input->getArgument('destination-environment');
    $destination_environment = $environments_resource->get($destination_environment_uuid);
    $this->checklist->completePreviousItem();

    $answer = $this->io->confirm("Are you sure that you want to overwrite everything on {$destination_environment->label} ({$destination_environment->name}) with source data from {$source_environment->label} ({$source_environment->name})");
    if (!$answer) {
      return 1;
    }

    if (!$input->getOption('no-code')) {
      $this->checklist->addItem("Initiating code switch");
      $code_copy_response = $acquia_cloud_client->request('post', "/environments/$destination_environment_uuid/code/actions/switch", [
        'form_params' => [
          'branch' => $source_environment->vcs->path,
        ],
      ]);
      $this->checklist->completePreviousItem();
    }

    if (!$input->getOption('no-databases')) {
      $this->checklist->addItem("Initiating database copy");
      $databases_resource = new Databases($acquia_cloud_client);
      $databases = $acquia_cloud_client->request('get', "/environments/$source_environment_uuid/databases");
      $default_database = $this->getDefaultDatabase($databases);

      // @todo Create database if its missing.
      $db_copy_response = $databases_resource->copy($source_environment_uuid, $default_database->name, $destination_environment_uuid);
      $this->checklist->completePreviousItem();
    }

    if (!$input->getOption('no-files')) {
      $this->checklist->addItem("Initiating files copy");
      $files_copy_response = $environments_resource->copyFiles($source_environment_uuid, $destination_environment_uuid);
      $this->checklist->completePreviousItem();
    }

    if (!$input->getOption('no-config')) {
      $this->checklist->addItem("Initiating config copy");
      $config = (array) $source_environment->configuration->php;
      $config['apcu'] = max(32, $source_environment->configuration->php->apcu);
      if ($config['version'] == $destination_environment->configuration->php->version) {
        unset($config['version']);
      }
      unset($config['memcached_limit']);
      $config_copy_response = $environments_resource->update($destination_environment_uuid, $config);
      $this->checklist->completePreviousItem();
    }

    if (isset($code_copy_response)) {
      $this->waitForNotificationSuccess($acquia_cloud_client, $code_copy_response->notification, 'Waiting for code copy to complete');
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
   * @param \stdClass $response
   *
   * @return string
   */
  protected function getNotificationUuidFromResponse($response): string {
    $notification_url = $response->links->notification->href;
    $url_parts = explode('/', $notification_url);
    return $url_parts[5];
  }

}