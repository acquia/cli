<?php

namespace Acquia\Ads\Command\Ide\Wizard;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Command\Ssh\SshKeyCommandBase;
use Acquia\Ads\Exception\AcquiaCliException;
use AcquiaCloudApi\Response\IdeResponse;

/**
 * Class IdeWizardCommandBase.
 */
abstract class IdeWizardCommandBase extends SshKeyCommandBase {

  /**
   *
   * @param \AcquiaCloudApi\Response\IdeResponse $ide
   *
   * @return string
   */
  public function getIdeSshKeyLabel(IdeResponse $ide): string {
    return SshKeyCommandBase::normalizeSshKeyLabel('IDE_' . $ide->label . '_' . $ide->uuid);
  }

  /**
   * @throws \Acquia\Ads\Exception\AcquiaCliException
   */
  public function requireRemoteIdeEnvironment(): void {
    if (!CommandBase::isAcquiaRemoteIde()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Remote IDE');
    }
  }

  /**
   * @param string $ide_uuid
   *
   * @return string
   */
  public function getSshKeyFilename(string $ide_uuid): string {
    return 'id_rsa_acquia_ide_' . $ide_uuid;
  }

}
