<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaskWaitCommand extends CommandBase {

  // phpcs:ignore
  protected static $defaultName = 'app:task-wait';

  protected function configure(): void {
    $this->setDescription('Wait for a task to complete')
      ->addArgument('notification-uuid', InputArgument::REQUIRED, 'The task notification UUID or Cloud Platform API response containing a linked notification')
      ->setHelp('Accepts either a notification UUID or Cloud Platform API response as JSON string. The JSON string must contain the _links->notification->href property.')
      ->addUsage('acli app:task-wait "$(api:environments:domain-clear-caches [environmentId] [domain])"');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $notificationUuid = $this->getNotificationUuid($input);
    $this->waitForNotificationToComplete($this->cloudApiClientService->getClient(), $notificationUuid, "Waiting for task $notificationUuid to complete");
    return Command::SUCCESS;
  }

  private function getNotificationUuid(InputInterface $input): string {
    $notificationUuid = $input->getArgument('notification-uuid');
    $json = json_decode($notificationUuid, FALSE);
    if (json_last_error() === JSON_ERROR_NONE) {
      if (is_object($json) && property_exists($json, '_links') && property_exists($json->_links, 'notification') && property_exists($json->_links->notification, 'href')) {
        return $this->getNotificationUuidFromResponse($json);
      }
      throw new AcquiaCliException("Input JSON must contain the _links.notification.href property.");
    }

    return self::validateUuid($input->getArgument('notification-uuid'));
  }

}
