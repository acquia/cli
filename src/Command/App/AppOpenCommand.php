<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppOpenCommand extends CommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'app:open';

  protected function configure(): void {
    $this->setDescription('Opens your browser to view a given Cloud application')
      ->acceptApplicationUuid()
      ->setAliases(['open', 'o']);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!LocalMachineHelper::isBrowserAvailable()) {
      throw new AcquiaCliException('No browser is available on this machine');
    }
    $applicationUuid = $this->determineCloudApplication();
    $this->localMachineHelper->startBrowser('https://cloud.acquia.com/a/applications/' . $applicationUuid);

    return Command::SUCCESS;
  }

}
