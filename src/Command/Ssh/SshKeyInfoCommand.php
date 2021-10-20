<?php

namespace Acquia\Cli\Command\Ssh;

use AcquiaCloudApi\Endpoints\SshKeys;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyInfoCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:info';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Print information about an SSH key')
      ->addOption('cloud-key-uuid', 'uuid', InputOption::VALUE_REQUIRED);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_key = $this->determineCloudKey($acquia_cloud_client, 'Choose an SSH key to view');

    $sshKeys = new SshKeys($acquia_cloud_client);
    $sshKey = $sshKeys->get($cloud_key->uuid);
    $this->io->definitionList(
      ['SSH key property' => 'SSH key value'],
      new TableSeparator(),
      ['UUID' => $sshKey->uuid],
      ['Label' => $sshKey->label],
      ['Fingerprint' => $sshKey->fingerprint],
      ['Created at' => $sshKey->created_at],
    );

    $this->io->writeln('Public key');
    $this->io->writeln('----------');
    $this->io->writeln($sshKey->public_key);

    return 0;
  }

}
