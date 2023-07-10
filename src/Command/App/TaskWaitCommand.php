<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaskWaitCommand extends CommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'app:task-wait';

  protected function configure(): void {
    $this->setDescription('Wait for a task to complete')
      ->addArgument('notification-uuid', InputArgument::REQUIRED, 'The task notification UUID or Cloud Platform API response containing a linked notification')
      ->setHelp('Accepts either a notification UUID or Cloud Platform API response as JSON string. The JSON string must contain the _links->notification->href property.')
      ->addUsage('"$(acli api:environments:domain-clear-caches [environmentId] [domain])"');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $notificationUuid = $input->getArgument('notification-uuid');
    $success = $this->waitForNotificationToComplete($this->cloudApiClientService->getClient(), $notificationUuid, "Waiting for task $notificationUuid to complete");
    return $success ? Command::SUCCESS : Command::FAILURE;
  }

}
