<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use stdClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCommandBase.
 */
abstract class IdeWizardCommandBase extends SshKeyCommandBase {

  /** @var string */
  protected $passphraseFilepath;
  /**
   * @var false|string
   */
  protected $ideUuid;
  /**
   * @var string
   */
  protected $privateSshKeyFilename;
  /**
   * @var string
   */
  protected $privateSshKeyFilepath;
  /**
   * @var string
   */
  protected $publicSshKeyFilepath;
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.passphrase');
    $this->ideUuid = CommandBase::getThisCloudIdeUuid();
    $this->privateSshKeyFilename = $this->getSshKeyFilename($this->ideUuid);
    $this->privateSshKeyFilepath = $this->sshDir . '/' . $this->privateSshKeyFilename;
    $this->publicSshKeyFilepath = $this->privateSshKeyFilepath . '.pub';

    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $this->ide = $ides_resource->get($this->ideUuid);
  }

  /**
   *
   */
  protected function deleteLocalIdeSshKey(): void {
    $this->localMachineHelper->getFilesystem()->remove([
      $this->publicSshKeyFilepath,
      $this->privateSshKeyFilepath,
    ]);
  }

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
   * @param string $ide_uuid
   *
   * @return string
   */
  public function getSshKeyFilename(string $ide_uuid): string {
    return 'id_rsa_acquia_ide_' . $ide_uuid;
  }

}
