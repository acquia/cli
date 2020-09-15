<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Class ApiCommandBase.
 */
class ApiCommandBase extends CommandBase {
  /**
   * @var string
   */
  protected $method;

  /**
   * @var array
   */
  protected $responses;

  /**
   * @var array
   */
  protected $servers;

  /**
   * @var string
   */
  protected $path;

  /**
   * @var array
   */
  private $queryParams = [];

  /**
   * @var array
   */
  private $postParams = [];

  /** @var array  */
  private $pathParams = [];

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->convertApplicationAliastoUuid($input);
    $this->fillMissingApplicationUuid($input, $output);
    $this->convertEnvironmentAliasToUuid($input);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Build query from non-null options.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    if ($this->queryParams) {
      foreach ($this->queryParams as $key) {
        // We may have a queryParam that is used in the path rather than the query string.
        if ($input->hasOption($key) && $input->getOption($key) !== NULL) {
          $acquia_cloud_client->addQuery($key, $input->getOption($key));
        }
        elseif ($input->hasArgument($key) && $input->getArgument($key) !== NULL) {
          $acquia_cloud_client->addQuery($key, $input->getArgument($key));
        }
      }
    }
    if ($this->postParams) {
      foreach ($this->postParams as $param_name) {
        $param = $this->getParamFromInput($input, $param_name);
        $param_name = ApiCommandHelper::restoreRenamedParameter($param_name);
        $acquia_cloud_client->addOption('form_params', [$param_name => $param]);
      }
    }

    $path = $this->getRequestPath($input);
    $user_agent = sprintf("acli/%s", $this->getApplication()->getVersion());
    $acquia_cloud_client->addOption('headers', [
      'User-Agent' => $user_agent,
      'Accept'     => 'application/json',
    ]);

    try {
      $response = $acquia_cloud_client->request($this->method, $path);
      $exit_code = 0;
    }
    catch (ApiErrorException $exception) {
      $response = $exception->getResponseBody();
      $exit_code = 1;
    }
    // @todo Add syntax highlighting to json output.
    $contents = json_encode($response, JSON_PRETTY_PRINT);
    $this->output->writeln($contents);

    return $exit_code;
  }

  /**
   * @param string $method
   */
  public function setMethod($method): void {
    $this->method = $method;
  }

  /**
   * @param array $responses
   */
  public function setResponses($responses): void {
    $this->responses = $responses;
  }

  /**
   * @param array $servers
   */
  public function setServers($servers): void {
    $this->servers = $servers;
  }

  /**
   * @param string $path
   */
  public function setPath($path): void {
    $this->path = $path;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return string
   */
  protected function getRequestPath(InputInterface $input): string {
    $path = $this->path;
    $arguments = $input->getArguments();
    // The command itself is the first argument. Remove it.
    array_shift($arguments);
    foreach ($arguments as $key => $value) {
      $token = '{' . $key . '}';
      if (strpos($path, $token) !== FALSE) {
        $path = str_replace($token, $value, $path);
      }
    }

    return $path;
  }

  /**
   * @return string
   */
  public function getMethod(): string {
    return $this->method;
  }

  /**
   * @param $param_name
   */
  public function addPostParameter($param_name): void {
    $this->postParams[] = $param_name;
  }

  /**
   * @param $param_name
   */
  public function addQueryParameter($param_name): void {
    $this->queryParams[] = $param_name;
  }

  /**
   * @return string
   */
  public function getPath(): string {
    return $this->path;
  }

  /**
   * @param $param_name
   */
  public function addPathParameter($param_name): void {
    $this->pathParams[] = $param_name;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param $param_name
   *
   * @return bool|string|string[]|null
   */
  protected function getParamFromInput(InputInterface $input, $param_name) {
    if ($input->hasArgument($param_name)) {
      $param = $input->getArgument($param_name);
    }
    else {
      $param = $input->getOption($param_name);
    }
    return $param;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function fillMissingApplicationUuid(InputInterface $input, OutputInterface $output): void {
    if ($input->hasArgument('applicationUuid') && !$input->getArgument('applicationUuid')) {
      $output->writeln('Inferring Cloud Application UUID for this command since none was provided...', OutputInterface::VERBOSITY_VERBOSE);
      if ($application_uuid = $this->determineCloudApplication()) {
        $output->writeln("Set application uuid to <options=bold>$application_uuid</>", OutputInterface::VERBOSITY_VERBOSE);
        $input->setArgument('applicationUuid', $application_uuid);
      }
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function convertApplicationAliastoUuid(InputInterface $input): void {
    if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
      $application_uuid_argument = $input->getArgument('applicationUuid');
      try {
        $this->validateUuid($application_uuid_argument);
      } catch (ValidatorException $validator_exception) {
        // Since this isn't a valid UUID, let's see if it's a valid alias.
        try {
          $customer_application = $this->getApplicationFromAlias($application_uuid_argument);
          $input->setArgument('applicationUuid', $customer_application->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("The {applicationUuid} must be a valid UUID or site alias.");
        }
      }
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function convertEnvironmentAliasToUuid(InputInterface $input): void {
    if ($input->hasArgument('environmentId') && $input->getArgument('environmentId')) {
      $env_uuid_argument = $input->getArgument('environmentId');
      try {
        // Environment IDs take the form of [env-num]-[app-uuid].
        $uuid_parts = explode('-', $env_uuid_argument);
        $env_id = $uuid_parts[0];
        unset($uuid_parts[0]);
        $application_uuid = implode('-', $uuid_parts);
        $this->validateUuid($application_uuid);
      } catch (ValidatorException $validator_exception) {
        try {
          // Since this isn't a valid environment ID, let's see if it's a valid alias.
          $this->validateEnvironmentAlias($env_uuid_argument);
          $environment = $this->getEnvironmentFromAliasArg($env_uuid_argument);
          $input->setArgument('environmentId', $environment->uuid);
        } catch (AcquiaCliException $exception) {
          throw new AcquiaCliException("The {environmentId} must be a valid UUID or site alias.");
        }
      }
    }
  }

}
