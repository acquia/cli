<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

class IdeServiceStartCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:service-start';

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Start a service in the Cloud IDE')
      ->addArgument('service', InputArgument::REQUIRED, 'The name of the service to start')
      ->addUsage('php')
      ->addUsage('apache')
      ->addUsage('mysql')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->requireCloudIdeEnvironment();
    $service = $input->getArgument('service');
    $this->validateService($service);

    $service_name_map = [
      'php' => 'php-fpm',
      'php-fpm' => 'php-fpm',
      'apache' => 'apache2',
      'apache2' => 'apache2',
      'mysql' => 'mysqld',
      'mysqld' => 'mysqld',
    ];
    $output->writeln("Starting <options=bold>$service</>...");
    $service_name = $service_name_map[$service];
    $this->startService($service_name);
    $output->writeln("<info>Started <options=bold>$service</></info>");

    return 0;
  }

  private function validateService(string $service): void {
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
