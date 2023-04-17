<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaskWaitCommand extends CommandBase {

  protected static $defaultName = 'app:task-wait';

  protected function configure(): void {
    $this->setDescription('Wait for a task to complete')
      ->addArgument('notification-uuid', InputArgument::REQUIRED, 'The UUID of the task notification returned by the Cloud API')
      ->setHelp('This command will accepts either a notification uuid as an argument or else a json string passed through standard input. The json string must contain the _links->notification->href property.')
      ->addUsage('acli app:task-wait "$(api:environments:domain-clear-caches [environmentId] [domain])"');
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $notification_uuid = $this->getNotificationUuid($input);
    $this->waitForNotificationToComplete($this->cloudApiClientService->getClient(), $notification_uuid, "Waiting for task $notification_uuid to complete");
    return 0;
  }

  private function getNotificationUuid(InputInterface $input): string {
    $notification_uuid = $input->getArgument('notification-uuid');
    $json = json_decode($notification_uuid, FALSE);
    if (json_last_error() === JSON_ERROR_NONE) {
      if (property_exists($json, '_links') && property_exists($json->_links, 'notification') && property_exists($json->_links->notification, 'href')) {
        return $this->getNotificationUuidFromResponse($json);
      }
      throw new AcquiaCliException("Input JSON must contain the _links.notification.href property.");
    }

    return self::validateUuid($input->getArgument('notification-uuid'));
  }

}
