<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exception\AdsException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class SshKeyDeleteCommand.
 */
class SshKeyDeleteCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ssh-key:delete')->setDescription('Delete an SSH key');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Ads\Exception\AdsException
   * @throws \Acquia\Ads\Exception\AdsException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->getAcquiaCloudClient();
    $response = $acquia_cloud_client->makeRequest('get', '/account/ssh-keys');
    $cloud_keys = $acquia_cloud_client->processResponse($response);
    $cloud_key = $this->promptChooseFromObjects(
      $cloud_keys,
      'uuid',
      'label',
      '<question>Choose an SSH key to delete from Acquia Cloud</question>:'
    );
    $response = $acquia_cloud_client->makeRequest('delete', '/account/ssh-keys/' . $cloud_key->uuid);
    if ($response->getStatusCode() == 202) {
      return 0;
    }

    throw new AdsException($response->getBody()->getContents());
  }

}
