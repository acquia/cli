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
   * @return \stdClass|null
   * @throws \Exception
   */
  protected function findIdeSshKeyOnCloud(): ?stdClass {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($this::getThisCloudIdeUuid());
    $ssh_key_label = $this->getIdeSshKeyLabel($ide);
    foreach ($cloud_keys as $cloud_key) {
      if ($cloud_key->label === $ssh_key_label) {
        return $cloud_key;
      }
    }
    return NULL;
  }

  /**
   * @param \stdClass|null $cloud_key
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function deleteSshKeyFromCloud(stdClass $cloud_key): void {
    $return_code = $this->executeAcliCommand('ssh-key:delete', [
      '--cloud-key-uuid' => $cloud_key->uuid,
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to delete SSH key from Acquia Cloud');
    }
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
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function requireCloudIdeEnvironment(): void {
    if (!CommandBase::isAcquiaCloudIde()) {
      throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
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
