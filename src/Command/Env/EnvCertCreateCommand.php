<?php

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvCertCreateCommand extends CommandBase {

  protected static $defaultName = 'env:certificate-create';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Install an SSL certificate.')
      ->addArgument('certificate', InputArgument::REQUIRED, 'Filename of the SSL certificate being installed.')
      ->addArgument('private_key', InputArgument::REQUIRED, 'Filename of the SSL private key.')
      ->addOption('legacy', '', InputOption::VALUE_OPTIONAL, 'Must be set to true for legacy certificates', FALSE)
      ->addOption('ca_certificates', '', InputOption::VALUE_OPTIONAL, 'Filename of the CA intermediary certificates.')
      ->addOption('csr_id', '', InputOption::VALUE_OPTIONAL, 'The CSR (certificate signing request) to associate with this certificate. Optional.')
      ->addOption('label', '', InputOption::VALUE_OPTIONAL, 'The label for this certificate. Required for standard certificates. Optional for legacy certificates.', 'My certificate')
      ->acceptEnvironmentId();
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environment_uuid = $this->determineCloudEnvironment();
    $sslCertificates = new SslCertificates($acquia_cloud_client);
    $sslCertificates->create(
      $environment_uuid,
      $input->getOption('label'),
      $this->localMachineHelper->readFile($input->getArgument('certificate')),
      $this->localMachineHelper->readFile($input->getArgument('private_key')),
      $this->localMachineHelper->readFile($input->getOption('ca_certificates')),
      $input->getOption('csr_id'),
      $input->getOption('legacy')
    );
    $this->io->success('Certificate was installed');
    return 0;
  }

}
