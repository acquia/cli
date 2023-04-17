<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Ides;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IdeShareCommand extends CommandBase {

  protected static $defaultName = 'ide:share';

  /**
   * @var array
   */
  private array $shareCodeFilepaths;

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Get the share URL for a Cloud IDE')
      ->addOption('regenerate', '', InputOption::VALUE_NONE, 'regenerate the share code')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();

    if ($input->getOption('regenerate')) {
      $this->regenerateShareCode();
    }

    $share_uuid = $this->localMachineHelper->readFile($this->getShareCodeFilepaths()[0]);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($this::getThisCloudIdeUuid());

    $this->output->writeln('');
    $this->output->writeln("<comment>Your IDE Share URL:</comment> <href={$ide->links->web->href}>{$ide->links->web->href}?share=$share_uuid</>");

    return 0;
  }

  /**
   * @param array $file_path
   */
  public function setShareCodeFilepaths(array $file_path): void {
    $this->shareCodeFilepaths = $file_path;
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
    $new_share_code = Uuid::uuid4();
    foreach ($this->getShareCodeFilepaths() as $path) {
      $this->localMachineHelper->writeFile($path, $new_share_code);
    }
  }

}
