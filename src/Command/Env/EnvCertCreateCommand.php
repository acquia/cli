<?php

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Symfony\Component\Console\Command\Command;
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
      ->addOption('certificate', '', InputOption::VALUE_REQUIRED, 'Filename of the SSL certificate being installed')
      ->addOption('private-key', '', InputOption::VALUE_REQUIRED, 'Filename of the SSL private key')
      ->addOption('legacy', '', InputOption::VALUE_OPTIONAL, 'True for legacy certificates', FALSE)
      ->addOption('ca-certificates', '', InputOption::VALUE_OPTIONAL, 'Filename of the CA intermediary certificates')
      ->addOption('csr-id', '', InputOption::VALUE_OPTIONAL, 'The CSR (certificate signing request) to associate with this certificate')
      ->addOption('label', '', InputOption::VALUE_OPTIONAL, 'The label for this certificate. Required for standard certificates. Optional for legacy certificates', 'My certificate')
      ->acceptEnvironmentId();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $envUuid = $this->determineCloudEnvironment();
    $certificate = $this->determineOption('certificate');
    $privateKey = $this->determineOption('private-key');
    $label = $this->determineOption('label');
    $caCertificates = $this->determineOption('ca-certificates');
    $csrId = (int) $this->determineOption('csr-id');
    $legacy = $this->determineOption('legacy', FALSE, NULL, NULL, 'false');
    $legacy = filter_var($legacy, FILTER_VALIDATE_BOOLEAN);

    $sslCertificates = new SslCertificates($acquiaCloudClient);
    $response = $sslCertificates->create(
      $envUuid,
      $label,
      $this->localMachineHelper->readFile($certificate),
      $this->localMachineHelper->readFile($privateKey),
      $caCertificates ? $this->localMachineHelper->readFile($caCertificates) : NULL,
      $csrId,
      $legacy
    );
    $notificationUuid = $this->getNotificationUuidFromResponse($response);
    $this->waitForNotificationToComplete($acquiaCloudClient, $notificationUuid, 'Installing certificate');
    return Command::SUCCESS;
  }

}
