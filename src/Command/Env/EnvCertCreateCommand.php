<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'env:certificate-create', description: 'Install an SSL certificate. (Added in 2.10.0)')]
final class EnvCertCreateCommand extends CommandBase
{
    protected function configure(): void
    {
        $this
            ->addArgument('certificate', InputArgument::REQUIRED, 'Filename of the SSL certificate being installed')
            ->addArgument('private-key', InputArgument::REQUIRED, 'Filename of the SSL private key')
            ->addOption('legacy', '', InputOption::VALUE_OPTIONAL, 'True for legacy certificates', false)
            ->addOption('ca-certificates', '', InputOption::VALUE_OPTIONAL, 'Filename of the CA intermediary certificates')
            ->addOption('csr-id', '', InputOption::VALUE_OPTIONAL, 'The CSR (certificate signing request) to associate with this certificate')
            ->addOption('label', '', InputOption::VALUE_OPTIONAL, 'The label for this certificate. Required for standard certificates. Optional for legacy certificates', 'My certificate')
            ->acceptEnvironmentId();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $environment = $this->determineEnvironment($input, $output, true, true);
        $certificate = $input->getArgument('certificate');
        $privateKey = $input->getArgument('private-key');
        $label = $this->determineOption('label');
        $caCertificates = $this->determineOption('ca-certificates');
        $csrId = (int) $this->determineOption('csr-id');
        $legacy = $this->determineOption('legacy', false, null, null, 'false');
        $legacy = filter_var($legacy, FILTER_VALIDATE_BOOLEAN);

        $sslCertificates = new SslCertificates($acquiaCloudClient);
        $response = $sslCertificates->create(
            $environment->uuid,
            $label,
            $this->localMachineHelper->readFile($certificate),
            $this->localMachineHelper->readFile($privateKey),
            $caCertificates ? $this->localMachineHelper->readFile($caCertificates) : null,
            $csrId,
            $legacy
        );
        $notificationUuid = CommandBase::getNotificationUuidFromResponse($response);
        $success = $this->waitForNotificationToComplete($acquiaCloudClient, $notificationUuid, 'Installing certificate');
        if (!$success) {
            throw new AcquiaCliException('Cloud API failed to install certificate');
        }
        return Command::SUCCESS;
    }
}
