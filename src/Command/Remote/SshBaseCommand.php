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
   */
  protected function getEnvFromAlias(
        $drush_site,
        $drush_env
    ): EnvironmentResponse {
    $this->logger->debug("Searching for an environment matching alias $drush_site.$drush_env.");
    $acquia_cloud_client = $this->getApplication()->getContainer()->get('cloud_api')->getClient();
    $acquia_cloud_client->addQuery('filter', 'hosting=@*' . $drush_site);
    $customer_applications = $acquia_cloud_client->request(
      'get',
      '/applications'
    );
    // @todo Throw exception if not found.
    $customer_application = $customer_applications[0];
    $acquia_cloud_client->clearQuery();
    $environments_resource = new Environments($acquia_cloud_client);
    $site_id = $customer_application->hosting->id;
    $parts = explode(':', $site_id);
    $site_prefix = $parts[1];
    if ($site_prefix === $drush_site) {
      $this->logger->debug("Found application matching $drush_site. Searching environments...");
      $environments = $environments_resource->getAll($customer_application->uuid);
      foreach ($environments as $environment) {
        if ($environment->name === $drush_env) {
          // @todo Create a cache entry for this alias.
          $this->logger->debug("Found environment matching $drush_env.");

          return $environment;
        }
      }
    }

    return NULL;
  }

}
