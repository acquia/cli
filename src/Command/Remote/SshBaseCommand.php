<?php

namespace Acquia\Cli\Command\Remote;

;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class SSHBaseCommand
 * Base class for Acquia CLI commands that deal with sending SSH commands.
 *
 * @package Acquia\Cli\Commands\Remote
 */
abstract class SshBaseCommand extends CommandBase {

  /**
   * @var \AcquiaCloudApi\Response\EnvironmentResponse
   */
  protected $environment;

  /**
   * Execute the command remotely.
   *
   * @param array $command_args
   *
   * @return int
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function executeCommand(array $command_args): int {
    $command_summary = $this->getCommandSummary($command_args);

    // Remove site_env arg.
    unset($command_args['alias']);
    $process = $this->sendCommandViaSsh($command_args);

    /** @var \Acquia\Cli\AcquiaCliApplication $application */
    $application = $this->getApplication();
    $application->getLogger()->notice('Command: {command} [Exit: {exit}]', [
      'env' => $this->environment->name,
      'command' => $command_summary,
      'exit' => $process->getExitCode(),
    ]);

    if (!$process->isSuccessful()) {
      throw new AcquiaCliException($process->getOutput());
    }

    return $process->getExitCode();
  }

  /**
   * Sends a command to an environment via SSH.
   *
   * @param array $command
   *   The command to be run on the platform.
   *
   * @return \Symfony\Component\Process\Process
   */
  protected function sendCommandViaSsh($command): Process {
    $this->getApplication()->getLocalMachineHelper()->setIsTty(TRUE);
    $command = $this->getSshCommand($command);

    return $this->getApplication()
      ->getLocalMachineHelper()
      ->execute($command, $this->getOutputCallback());
  }

  /**
   * Return the first item of the $command_args that is not an option.
   *
   * @param array $command_args
   *
   * @return string
   */
  private function firstArguments($command_args): string {
    $result = '';
    while (!empty($command_args)) {
      $first = array_shift($command_args);
      if (strlen($first) && $first[0] == '-') {
        return $result;
      }
      $result .= " $first";
    }

    return $result;
  }

  /**
   * @return \Closure
   */
  private function getOutputCallback(): callable {
    if ($this->getApplication()->getLocalMachineHelper()->useTty() === FALSE) {
      $output = $this->output;

      return static function ($type, $buffer) use ($output) {
        $output->write($buffer);
      };
    }

    return static function ($type, $buffer) {};
  }

  /**
   * Return a summary of the command that does not include the
   * arguments. This avoids potential information disclosure in
   * CI scripts.
   *
   * @param array $command_args
   *
   * @return string
   */
  private function getCommandSummary($command_args): string {
    return $this->firstArguments($command_args);
  }

  /**
   * @return array SSH connection string
   */
  private function getConnectionArgs(): array {
    return [
      'ssh',
      //'-T',
      $this->environment->sshUrl,
      '-o StrictHostKeyChecking=no',
      '-o AddressFamily inet',
    ];
  }

  /**
   * @param string $alias
   *
   * @return string
   */
  protected function validateAlias($alias): string {
    $violations = Validation::createValidator()->validate($alias, [
      new Length(['min' => 5]),
      new NotBlank(),
      new Regex(['pattern' => '/.+\..+/', 'message' => 'Alias must match pattern `[app-name].[env]']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $alias;
  }

  /**
   * @param $alias
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getEnvironmentFromAliasArg($alias): EnvironmentResponse {
    $site_env_parts = explode('.', $alias);
    [$drush_site, $drush_env] = $site_env_parts;

    return $this->getEnvFromAlias($drush_site, $drush_env);
  }

  /**
   * @param string $drush_site
   * @param string $drush_env
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getEnvFromAlias(
        $drush_site,
        $drush_env
    ): EnvironmentResponse {
    // @todo Speed this up with some kind of caching.
    $this->logger->debug("Searching for an environment matching alias $drush_site.$drush_env.");
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    $environments_resource = new Environments($acquia_cloud_client);
    foreach ($customer_applications as $customer_application) {
      $site_id = $customer_application->hosting->id;
      $parts = explode(':', $site_id);
      $site_prefix = $parts[1];
      if ($site_prefix === $drush_site) {
        $this->logger->debug("Found application matching $drush_site. Searching environments...");
        $environments = $environments_resource->getAll($customer_application->uuid);
        foreach ($environments as $environment) {
          if ($environment->name === $drush_env) {
            $this->logger->debug("Found environment matching $drush_env.");

            return $environment;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * @param $command
   *
   * @return array
   */
  protected function getSshCommand($command): array {
    return array_merge($this->getConnectionArgs(), $command);
  }

}
