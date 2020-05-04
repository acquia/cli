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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardDeleteSshKeyCommand.
 */
class IdeWizardDeleteSshKeyCommand extends IdeWizardCommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ide:wizard:ssh-key:delete')
      ->setDescription('Wizard to delete SSH key for IDE from Cloud')
      ->setHidden(!CommandBase::isAcquiaRemoteIde());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Ads\Exception\AdsException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->requireRemoteIdeEnvironment();

    $acquia_cloud_client = $this->getAcquiaCloudClient();
    $cloud_key = $this->findIdeSshKeyOnCloud($acquia_cloud_client);
    if (!$cloud_key) {
      throw new AdsException('Could not find an SSH key on Acquia Cloud matching any local key in this IDE.');
    }

    $command = $this->getApplication()->find('ssh-key:delete');
    $arguments = [
      'command' => $command->getName(),
      '--cloud-key-uuid'  => $cloud_key->uuid,
    ];
    $upload_input = new ArrayInput($arguments);
    $returnCode = $command->run($upload_input, $output);
    if ($returnCode !== 0) {
      throw new AdsException('Unable to delete SSH key from Acquia Cloud');
    }

    $local_key = $this->findMatchingLocalKey($cloud_key);
    $public_ssh_key_path = $local_key->getRealPath();
    unlink($public_ssh_key_path);
    $private_ssh_key_path = str_replace('.pub', '', $public_ssh_key_path);
    unlink($private_ssh_key_path);
    $this->output->writeln("<info>Deleted local files <comment>$public_ssh_key_path</comment> and <comment>$private_ssh_key_path</comment>");

    return 0;
  }

  /**
   * @param $cloud_key
   *
   * @return mixed|\Symfony\Component\Finder\SplFileInfo|null
   */
  protected function findMatchingLocalKey($cloud_key) {
    $local_keys = $this->findLocalSshKeys();
    foreach ($local_keys as $local_index => $local_file) {
      if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
        return $local_file;
      }
    }
    return NULL;
  }

  /**
   * @param $acquia_cloud_client
   *
   * @return \stdClass|null
   */
  protected function findIdeSshKeyOnCloud($acquia_cloud_client): ?\stdClass {
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $ssh_key_label = $this->getIdeSshKeyLabel($this::getThisRemoteIdeUuid());
    foreach ($cloud_keys as $cloud_key) {
      if ($cloud_key->label === $ssh_key_label) {
        return $cloud_key;
      }
    }
    return NULL;
  }

}
