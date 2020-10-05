<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class IdeServiceRestartCommand.
 */
class IdeServiceRestartCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:service-restart';

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Restart a service in the Cloud IDE')
      ->addArgument('service', InputArgument::REQUIRED, 'The name of the service to restart')
      ->addUsage('php')
      ->addUsage('apache')
      ->addUsage('mysql')
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->requireCloudIdeEnvironment();
    $service = $input->getArgument('service');
    $this->validateService($service);
    $this->restartService($service);

    return 0;
  }

  /**
   * @param string $service
   *
   * @return mixed
   */
  protected function validateService($service) {
    $violations = Validation::createValidator()->validate($service, [
      new Choice([
        'choices' => ['php', 'apache', 'mysql'],
        'message' => 'Pleases specify a valid service name: php, apache, or mysql',
      ]),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $service;
  }

  /**
   * Restart Apache inside IDE.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function restartService($service): void {
    $this->logger->notice("Restarting $service...");
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'restart',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to restart ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

}
