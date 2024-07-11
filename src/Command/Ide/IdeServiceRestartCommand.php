<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ide;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

#[AsCommand(name: 'ide:service-restart', description: 'Restart a service in the Cloud IDE')]
final class IdeServiceRestartCommand extends IdeCommandBase
{
    protected function configure(): void
    {
        $this
        ->addArgument('service', InputArgument::REQUIRED, 'The name of the service to restart')
        ->addUsage('php')
        ->addUsage('apache')
        ->addUsage('mysql')
        ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->requireCloudIdeEnvironment();
        $service = $input->getArgument('service');
        $this->validateService($service);

        $serviceNameMap = [
            'apache' => 'apache2',
            'apache2' => 'apache2',
            'mysql' => 'mysqld',
            'mysqld' => 'mysqld',
            'php' => 'php-fpm',
            'php-fpm' => 'php-fpm',
        ];
        $output->writeln("Restarting <options=bold>$service</>...");
        $serviceName = $serviceNameMap[$service];
        $this->restartService($serviceName);
        $output->writeln("<info>Restarted <options=bold>$service</></info>");

        return Command::SUCCESS;
    }

    private function validateService(string $service): void
    {
        $violations = Validation::createValidator()->validate($service, [
            new Choice([
                'choices' => ['php', 'php-fpm', 'apache', 'apache2', 'mysql', 'mysqld'],
                'message' => 'Specify a valid service name: php, apache, or mysql',
            ]),
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }
    }
}
