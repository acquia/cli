<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\AcquiaCliApplication;
use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyDeleteCommand.
 */
class SshKeyDeleteCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ssh-key:delete')
      ->setDescription('Delete an SSH key')
      ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Ads\Exception\AcquiaCliException
   * @throws \Acquia\Ads\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $cloud_key = $this->determineCloudKey($acquia_cloud_client);

    $response = $acquia_cloud_client->makeRequest('delete', '/account/ssh-keys/' . $cloud_key->uuid);
    if ($response->getStatusCode() === 202) {
      $output->writeln("<info>Successfully deleted SSH key <comment>{$cloud_key->label}</comment> from Acquia Cloud.</info>");
      return 0;
    }

    throw new AcquiaCliException($response->getBody()->getContents());
  }

  protected function determineCloudKey($acquia_cloud_client) {
    if ($this->input->getOption('cloud-key-uuid')) {
      $cloud_key_uuid = $this->input->getOption('cloud-key-uuid');
      $cloud_key = $acquia_cloud_client->request('get', '/account/ssh-keys/' . $cloud_key_uuid);
      return $cloud_key;
    }

    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $cloud_key = $this->promptChooseFromObjects(
      $cloud_keys,
      'uuid',
      'label',
      '<question>Choose an SSH key to delete from Acquia Cloud</question>:'
    );
    return $cloud_key;
  }

}
