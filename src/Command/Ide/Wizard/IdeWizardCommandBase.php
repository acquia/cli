<?php

namespace Acquia\Ads\Command\Ide\Wizard;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Command\Ssh\SshKeyCommandBase;
use Acquia\Ads\Exception\AdsException;
use Acquia\Ads\Output\Checklist;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\EnvironmentResponse;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCommandBase.
 */
abstract class IdeWizardCommandBase extends SshKeyCommandBase {

  /**
   *
   * @param $uuid
   *
   * @return string
   */
  public function getIdeSshKeyLabel($uuid): string {
    // @todo Add IDE label to this!
    return SshKeyCommandBase::normalizeSshKeyLabel('Remote_IDE_' . $uuid);
  }

  /**
   * @throws \Acquia\Ads\Exception\AdsException
   */
  public function requireRemoteIdeEnvironment(): void {
    if (!CommandBase::isAcquiaRemoteIde()) {
      throw new AdsException('This command can only be run inside of an Acquia Remote IDE');
    }
  }

}
