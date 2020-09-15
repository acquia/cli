<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeShareCommand.
 */
class IdeShareCommand extends CommandBase {

  protected static $defaultName = 'ide:share';

  /**
   * @var string
   */
  private $shareCodeFilepath;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Get the share URL for a Cloud IDE')
      ->addOption('regenerate', '', InputOption::VALUE_NONE, 'regenerate the share code')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->requireCloudIdeEnvironment();

    if ($input->getOption('regenerate')) {
      $this->regenerateShareCode();
    }

    $share_uuid = $this->localMachineHelper->readFile($this->getShareCodeFilepath());
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($this::getThisCloudIdeUuid());

    $this->output->writeln('');
    $this->output->writeln("<comment>Your IDE Share URL:</comment> <href={$ide->links->ide->href}>{$ide->links->ide->href}?share=$share_uuid</>");

    return 0;
  }

  /**
   * @param string $file_path
   */
  public function setShareCodeFilepath($file_path): void {
    $this->shareCodeFilepath = $file_path;
  }

  /**
   * @return string
   */
  public function getShareCodeFilepath(): string {
    if (!isset($this->shareCodeFilepath)) {
      $this->shareCodeFilepath = '/usr/local/share/ide/.sharecode';
    }
    return $this->shareCodeFilepath;
  }

  /**
   * @throws \Exception
   */
  public function regenerateShareCode(): void {
    $new_share_code = md5(random_bytes(10));
    $this->localMachineHelper->writeFile($this->getShareCodeFilepath(), $new_share_code);

    return $new_share_code;
  }

}
