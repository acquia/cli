<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class IdeWizardCommandBase extends WizardCommandBase {

  use SshCommandTrait;

  protected string|false $ideUuid;

  protected IdeResponse $ide;

  /**
   * Initializes the command just after the input has been validated.
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
    parent::initialize($input, $output);

    $this->setSshKeyFilepath(self::getSshKeyFilename($this::getThisCloudIdeUuid()));
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.passphrase');
  }

  public static function getSshKeyFilename(mixed $ideUuid): string {
    return 'id_rsa_acquia_ide_' . $ideUuid;
  }

  protected function validateEnvironment(): void {
    $this->requireCloudIdeEnvironment();
  }

  protected function getSshKeyLabel(): string {
    return $this::getIdeSshKeyLabel(self::getThisCloudIdeLabel(), self::getThisCloudIdeUuid());
  }

  protected function deleteThisSshKeyFromCloud(mixed $output): void {
    if ($cloudKey = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeLabel(), $this::getThisCloudIdeUuid())) {
      $this->deleteSshKeyFromCloud($output, $cloudKey);
    }
  }

}
