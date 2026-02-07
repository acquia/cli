<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\OperationResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'env:mirror', description: 'Makes one environment identical to another in terms of code, database, files, and configuration. (Added in 2.0.0).')]
final class EnvMirrorCommand extends CommandBase
{
    private Checklist $checklist;

    protected function configure(): void
    {
        $this->addArgument('source-environment', InputArgument::REQUIRED, 'The Cloud Platform source environment ID or alias')
            ->addUsage('[<environmentAlias>]')
            ->addUsage('myapp.dev')
            ->addUsage('12345-abcd1234-1111-2222-3333-0e02b2c3d470');
        $this->addArgument('destination-environment', InputArgument::REQUIRED, 'The Cloud Platform destination environment ID or alias')
            ->addUsage('[<environmentAlias>]')
            ->addUsage('myapp.dev')
            ->addUsage('12345-abcd1234-1111-2222-3333-0e02b2c3d470');
        $this->addOption('no-code', 'c');
        $this->addOption('no-databases', 'd');
        $this->addOption('no-files', 'f');
        $this->addOption('no-config', 'p');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checklist = new Checklist($output);
        $outputCallback = $this->getOutputCallback($output, $this->checklist);
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $environmentsResource = new Environments($acquiaCloudClient);
        $sourceEnvironmentUuid = $input->getArgument('source-environment');
        $destinationEnvironmentUuid = $input->getArgument('destination-environment');

        $this->checklist->addItem("Fetching information about source environment");
        $sourceEnvironment = $environmentsResource->get($sourceEnvironmentUuid);
        $this->checklist->completePreviousItem();

        $this->checklist->addItem("Fetching information about destination environment");
        $destinationEnvironment = $environmentsResource->get($destinationEnvironmentUuid);
        $this->checklist->completePreviousItem();

        $answer = $this->io->confirm("Are you sure that you want to overwrite everything on $destinationEnvironment->label ($destinationEnvironment->name) and replace it with source data from $sourceEnvironment->label ($sourceEnvironment->name)");
        if (!$answer) {
            return 1;
        }

        if (!$input->getOption('no-code')) {
            $codeCopyResponse = $this->mirrorCode($acquiaCloudClient, $destinationEnvironmentUuid, $sourceEnvironment, $outputCallback);
        }

        if (!$input->getOption('no-databases')) {
            $dbCopyResponse = $this->mirrorDatabase($acquiaCloudClient, $sourceEnvironmentUuid, $destinationEnvironmentUuid, $outputCallback);
        }

        if (!$input->getOption('no-files')) {
            $filesCopyResponse = $this->mirrorFiles($environmentsResource, $sourceEnvironmentUuid, $destinationEnvironmentUuid);
        }

        if (!$input->getOption('no-config')) {
            $configCopyResponse = $this->mirrorConfig($sourceEnvironment, $destinationEnvironment, $environmentsResource, $destinationEnvironmentUuid, $outputCallback);
        }

        if (isset($codeCopyResponse) && !$this->waitForNotificationToComplete($acquiaCloudClient, CommandBase::getNotificationUuidFromResponse($codeCopyResponse), 'Waiting for code copy to complete')) {
            throw new AcquiaCliException('Cloud API failed to copy code');
        }
        if (isset($dbCopyResponse) && !$this->waitForNotificationToComplete($acquiaCloudClient, CommandBase::getNotificationUuidFromResponse($dbCopyResponse), 'Waiting for database copy to complete')) {
            throw new AcquiaCliException('Cloud API failed to copy database');
        }
        if (isset($filesCopyResponse) && !$this->waitForNotificationToComplete($acquiaCloudClient, CommandBase::getNotificationUuidFromResponse($filesCopyResponse), 'Waiting for files copy to complete')) {
            throw new AcquiaCliException('Cloud API failed to copy files');
        }
        if (isset($configCopyResponse) && !$this->waitForNotificationToComplete($acquiaCloudClient, CommandBase::getNotificationUuidFromResponse($configCopyResponse), 'Waiting for config copy to complete')) {
            throw new AcquiaCliException('Cloud API failed to copy config');
        }

        $this->io->success([
            "Done! $destinationEnvironment->label now matches $sourceEnvironment->label",
            "You can visit it here:",
            "https://" . $destinationEnvironment->domains[0],
        ]);

        return Command::SUCCESS;
    }

    private function getDefaultDatabase(array $databases): ?object
    {
        foreach ($databases as $database) {
            if ($database->flags->default) {
                return $database;
            }
        }
        return null;
    }

    private function mirrorDatabase(Client $acquiaCloudClient, mixed $sourceEnvironmentUuid, mixed $destinationEnvironmentUuid, callable $outputCallback): OperationResponse
    {
        $this->checklist->addItem("Initiating database copy");
        $outputCallback('out', "Getting a list of databases");
        $databasesResource = new Databases($acquiaCloudClient);
        $databases = $acquiaCloudClient->request('get', "/environments/$sourceEnvironmentUuid/databases");
        $defaultDatabase = $this->getDefaultDatabase($databases);
        $outputCallback('out', "Copying $defaultDatabase->name");

        // @todo Create database if its missing.
        $dbCopyResponse = $databasesResource->copy($sourceEnvironmentUuid, $defaultDatabase->name, $destinationEnvironmentUuid);
        $this->checklist->completePreviousItem();
        return $dbCopyResponse;
    }

    private function mirrorCode(Client $acquiaCloudClient, mixed $destinationEnvironmentUuid, EnvironmentResponse $sourceEnvironment, callable $outputCallback): mixed
    {
        $this->checklist->addItem("Initiating code switch");
        $outputCallback('out', "Switching to {$sourceEnvironment->vcs->path}");
        $codeCopyResponse = $acquiaCloudClient->request('post', "/environments/$destinationEnvironmentUuid/code/actions/switch", [
            'form_params' => [
                'branch' => $sourceEnvironment->vcs->path,
            ],
        ]);
        $codeCopyResponse->links = $codeCopyResponse->_links;
        $this->checklist->completePreviousItem();
        return $codeCopyResponse;
    }

    private function mirrorFiles(Environments $environmentsResource, mixed $sourceEnvironmentUuid, mixed $destinationEnvironmentUuid): OperationResponse
    {
        $this->checklist->addItem("Initiating files copy");
        $filesCopyResponse = $environmentsResource->copyFiles($sourceEnvironmentUuid, $destinationEnvironmentUuid);
        $this->checklist->completePreviousItem();
        return $filesCopyResponse;
    }

    private function mirrorConfig(EnvironmentResponse $sourceEnvironment, EnvironmentResponse $destinationEnvironment, Environments $environmentsResource, mixed $destinationEnvironmentUuid, callable $outputCallback): OperationResponse
    {
        $this->checklist->addItem("Initiating config copy");
        $outputCallback('out', "Copying PHP version, acpu memory limit, etc.");
        $config = (array) $sourceEnvironment->configuration->php;
        $config['apcu'] = max(32, $sourceEnvironment->configuration->php->apcu);
        if ($config['version'] == $destinationEnvironment->configuration->php->version) {
            unset($config['version']);
        }
        unset($config['memcached_limit']);
        $configCopyResponse = $environmentsResource->update($destinationEnvironmentUuid, $config);
        $this->checklist->completePreviousItem();
        return $configCopyResponse;
    }
}
