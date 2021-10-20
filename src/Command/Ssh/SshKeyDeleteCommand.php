<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SshKeyDeleteCommand.
 */
class SshKeyDeleteCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:delete';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Delete an SSH key')
      ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_key = $this->determineCloudKey($acquia_cloud_client, 'Choose an SSH key to delete from the Cloud Platform');

    $response = $acquia_cloud_client->makeRequest('delete', '/account/ssh-keys/' . $cloud_key->uuid);
    if ($response->getStatusCode() === 202) {
      $output->writeln("<info>Successfully deleted SSH key <options=bold>{$cloud_key->label}</> from the Cloud Platform.</info>");
      return 0;
    }

    throw new AcquiaCliException($response->getBody()->getContents());
  }

}
