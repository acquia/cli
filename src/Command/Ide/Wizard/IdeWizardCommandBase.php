<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class IdeWizardCommandBase extends WizardCommandBase {

  use SshCommandTrait;

  protected string|false $ideUuid;

  protected IdeResponse $ide;

  /**
   * Initializes the command just after the input has been validated.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   An InputInterface instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   An OutputInterface instance.
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
    parent::initialize($input, $output);

    $this->ideUuid = $this::getThisCloudIdeUuid();
    $this->setSshKeyFilepath(self::getSshKeyFilename($this->ideUuid));
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.passphrase');
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $idesResource = new Ides($acquiaCloudClient);
    $this->ide = $idesResource->get($this->ideUuid);
  }

  public static function getSshKeyFilename(mixed $ideUuid): string {
    return 'id_rsa_acquia_ide_' . $ideUuid;
  }

  protected function validateEnvironment(): void {
    $this->requireCloudIdeEnvironment();
  }

  protected function getSshKeyLabel(): string {
    return $this::getIdeSshKeyLabel($this->ide);
  }

  protected function deleteThisSshKeyFromCloud(mixed $output): void {
    if ($cloudKey = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeUuid())) {
      $this->deleteSshKeyFromCloud($output, $cloudKey);
    }
  }

}
