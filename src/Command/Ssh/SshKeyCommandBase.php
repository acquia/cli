<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Response\IdeResponse;

/**
 * Class SshKeyCommandBase.
 */
abstract class SshKeyCommandBase extends CommandBase {

  /**
   * @return \Symfony\Component\Finder\SplFileInfo[]
   * @throws \Exception
   */
  protected function findLocalSshKeys(): array {
    $finder = $this->localMachineHelper->getFinder();
    $finder->files()->in($this->sshDir)->name('*.pub')->ignoreUnreadableDirs();
    return iterator_to_array($finder);
  }

  /**
   *
   * @param \AcquiaCloudApi\Response\IdeResponse $ide
   *
   * @return string
   */
  public static function getIdeSshKeyLabel(IdeResponse $ide): string {
    return self::normalizeSshKeyLabel('IDE_' . $ide->label . '_' . $ide->uuid);
  }

  /**
   * @param $label
   *
   * @return string|string[]|null
   */
  public static function normalizeSshKeyLabel($label) {
    // It may only contain letters, numbers and underscores,.
    $label = preg_replace('/[^A-Za-z0-9_]/', '', $label);

    return $label;
  }

  protected function determineCloudKey($acquia_cloud_client, string $question_text) {
    if ($this->input->getOption('cloud-key-uuid')) {
      $cloud_key_uuid = self::validateUuid($this->input->getOption('cloud-key-uuid'));
      $cloud_key = $acquia_cloud_client->request('get', '/account/ssh-keys/' . $cloud_key_uuid);
      return $cloud_key;
    }

    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $cloud_key = $this->promptChooseFromObjectsOrArrays(
      $cloud_keys,
      'uuid',
      'label',
      $question_text
    );
    return $cloud_key;
  }

}
