<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\WizardCommandBase;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCommandBase.
 */
abstract class IdeWizardCommandBase extends WizardCommandBase {
  /**
   * @var false|string
   */
  protected $ideUuid;
  /**
   * @var \AcquiaCloudApi\Response\IdeResponse
   */
  protected $ide;

  /**
   * Initializes the command just after the input has been validated.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   An InputInterface instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   An OutputInterface instance.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException|\Psr\Cache\InvalidArgumentException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);

    $this->ideUuid = $this::getThisCloudIdeUuid();
    $this->setSshKeyFilepath($this->getSshKeyFilename($this->ideUuid));
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $this->ide = $ides_resource->get($this->ideUuid);
  }

  /**
   * @param string $ide_uuid
   *
   * @return string
   */
  public function getSshKeyFilename(string $ide_uuid): string {
    return 'id_rsa_acquia_ide_' . $ide_uuid;
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function validateEnvironment() {
    $this->requireCloudIdeEnvironment();
  }

  /**
   * @return string
   */
  protected function getSshKeyLabel() {
    return $this::getIdeSshKeyLabel($this->ide);
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function deleteThisSshKeyFromCloud(): void {
    if ($cloud_key = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeUuid())) {
      $this->deleteSshKeyFromCloud($cloud_key);
    }
  }
}