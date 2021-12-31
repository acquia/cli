<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class AliasHelper {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * @var \Acquia\Cli\CloudApi\ClientService
   */
  private ClientService $cloudApiClientService;

  public function __construct(
    LoggerInterface $logger,
    ClientService $cloud_api_client_service
  ) {
    $this->logger = $logger;
    $this->cloudApiClientService = $cloud_api_client_service;
  }

  /**
   * @param string $alias
   *
   * @return string
   */
  public static function validateEnvironmentAlias(string $alias): string {
    $violations = Validation::createValidator()->validate($alias, [
      new Length(['min' => 5]),
      new NotBlank(),
      new Regex(['pattern' => '/.+\..+/', 'message' => 'Environment alias must match the pattern [app-name].[env]']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $alias;
  }

  /**
   * @param string $alias
   *
   * @return string
   */
  protected function normalizeAlias(string $alias): string {
    return str_replace('@', '', $alias);
  }

  /**
   * @param string $alias
   *
   * @return EnvironmentResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getEnvironmentFromAliasArg(string $alias): EnvironmentResponse {
    return $this->getEnvFromAlias($alias);
  }

  /**
   * @param $alias
   *
   * @return EnvironmentResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getEnvFromAlias($alias): EnvironmentResponse {
    $cache = self::getAliasCache();
    return $cache->get($alias, function (ItemInterface $item) use ($alias) {
      return $this->doGetEnvFromAlias($alias);
    });
  }

  /**
   * @param string $alias
   *
   * @return EnvironmentResponse
   * @throws AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function doGetEnvFromAlias(string $alias): EnvironmentResponse {
    $site_env_parts = explode('.', $alias);
    [$application_alias, $environment_alias] = $site_env_parts;
    $this->logger->debug("Searching for an environment matching alias $application_alias.$environment_alias.");
    $customer_application = $this->getApplicationFromAlias($application_alias);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $acquia_cloud_client->clearQuery();
    $environments_resource = new Environments($acquia_cloud_client);
    $environments = $environments_resource->getAll($customer_application->uuid);
    foreach ($environments as $environment) {
      if ($environment->name === $environment_alias) {
        $this->logger->debug("Found environment {$environment->uuid} matching $environment_alias.");

        return $environment;
      }
    }

    throw new AcquiaCliException("Environment not found matching the alias {alias}", ['alias' => "$application_alias.$environment_alias"]);
  }

  /**
   * @param string $application_alias
   *
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getApplicationFromAlias(string $application_alias) {
    $cache = self::getAliasCache();
    return $cache->get($application_alias, function (ItemInterface $item) use ($application_alias) {
      return $this->doGetApplicationFromAlias($application_alias);
    });
  }

  /**
   * Return the ACLI alias cache.
   * @return FilesystemAdapter
   */
  public static function getAliasCache(): FilesystemAdapter {
    return new FilesystemAdapter('acli_aliases');
  }

  /**
   * @param string $application_alias
   *
   * @return \AcquiaCloudApi\Response\ApplicationResponse
   * @throws AcquiaCliException
   */
  protected function doGetApplicationFromAlias(string $application_alias): ApplicationResponse {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $acquia_cloud_client->addQuery('filter', 'hosting=@*:' . $application_alias);
    // Allow Cloud users with 'support' role to resolve aliases for applications to
    // which they don't explicitly belong.
    $account_resource = new Account($acquia_cloud_client);
    $account = $account_resource->get();
    if ($account->flags->support) {
      $acquia_cloud_client->addQuery('all', 'true');
    }
    $customer_applications = $acquia_cloud_client->request('get', '/applications');
    $site_prefix = '';
    if ($customer_applications) {
      $customer_application = $customer_applications[0];
      $site_id = $customer_application->hosting->id;
      $parts = explode(':', $site_id);
      $site_prefix = $parts[1];
    }
    else {
      throw new AcquiaCliException("No applications found");
    }

    if ($site_prefix !== $application_alias) {
      throw new AcquiaCliException("Application not found matching the alias {alias}", ['alias' => $application_alias]);
    }

    $this->logger->debug("Found application {$customer_application->uuid} matching alias $application_alias.");

    // Remove the host=@*.$drush_site query as it would persist for future requests.
    $acquia_cloud_client->clearQuery();

    return $customer_application;
  }

  /**
   * @param InputInterface $input
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException|\Psr\Cache\InvalidArgumentException
   */
  public function convertApplicationAliasToUuid(InputInterface $input): void {
    if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
      $application_uuid_argument = $input->getArgument('applicationUuid');
      try {
        CommandBase::validateUuid($application_uuid_argument);
      } catch (ValidatorException $validator_exception) {
        // Since this isn't a valid UUID, let's see if it's a valid alias.
        $alias = $this->normalizeAlias($application_uuid_argument);
        try {
          $customer_application = $this->getApplicationFromAlias($alias);
          $input->setArgument('applicationUuid', $customer_application->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("The {applicationUuid} argument must be a valid UUID or application alias that is accessible to your Cloud user.");
        }
      }
    }
  }

  /**
   * @param InputInterface $input
   *
   * @param string $argument_name
   *
   * @throws AcquiaCliException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function convertEnvironmentAliasToUuid(InputInterface $input, string $argument_name): void {
    if ($input->hasArgument($argument_name) && $input->getArgument($argument_name)) {
      $env_uuid_argument = $input->getArgument($argument_name);
      try {
        // Environment IDs take the form of [env-num]-[app-uuid].
        $uuid_parts = explode('-', $env_uuid_argument);
        $env_id = $uuid_parts[0];
        unset($uuid_parts[0]);
        $application_uuid = implode('-', $uuid_parts);
        CommandBase::validateUuid($application_uuid);
      } catch (ValidatorException $validator_exception) {
        try {
          // Since this isn't a valid environment ID, let's see if it's a valid alias.
          $alias = $env_uuid_argument;
          $alias = $this->normalizeAlias($alias);
          $alias = self::validateEnvironmentAlias($alias);
          $environment = $this->getEnvironmentFromAliasArg($alias);
          $input->setArgument($argument_name, $environment->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("{{$argument_name}} must be a valid UUID or site alias.");
        }
      }
    }
  }

}
