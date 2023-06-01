<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Ides;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IdeShareCommand extends CommandBase {

  /**
   * @var string
   */
  protected static $defaultName = 'ide:share';

  /**
   * @var array
   */
  private array $shareCodeFilepaths;

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function configure(): void {
    $this->setDescription('Get the share URL for a Cloud IDE')
      ->addOption('regenerate', '', InputOption::VALUE_NONE, 'regenerate the share code')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();

    if ($input->getOption('regenerate')) {
      $this->regenerateShareCode();
    }

    $shareUuid = $this->localMachineHelper->readFile($this->getShareCodeFilepaths()[0]);
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $idesResource = new Ides($acquiaCloudClient);
    $ide = $idesResource->get($this::getThisCloudIdeUuid());

    $this->output->writeln('');
    $this->output->writeln("<comment>Your IDE Share URL:</comment> <href={$ide->links->web->href}>{$ide->links->web->href}?share=$shareUuid</>");

    return Command::SUCCESS;
  }

  public function setShareCodeFilepaths(array $filePath): void {
    $this->shareCodeFilepaths = $filePath;
  }

  /**
   * @return array
   */
  private function getShareCodeFilepaths(): array {
    if (!isset($this->shareCodeFilepaths)) {
      $this->shareCodeFilepaths = [
        '/usr/local/share/ide/.sharecode',
        '/home/ide/.sharecode',
      ];
    }
    return $this->shareCodeFilepaths;
  }

  private function regenerateShareCode(): void {
    $newShareCode = Uuid::uuid4();
    foreach ($this->getShareCodeFilepaths() as $path) {
      $this->localMachineHelper->writeFile($path, $newShareCode);
    }
  }

}
