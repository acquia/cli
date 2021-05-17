<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
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
      foreach ($this->queryParams as $key => $param_spec) {
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
      foreach ($this->postParams as $param_name => $param_spec) {
        $param = $this->getParamFromInput($input, $param_name);
        if (!is_null($param)) {
          $param_name = ApiCommandHelper::restoreRenamedParameter($param_name);
          if ($param_spec) {
            $param = $this->castParamType($param_spec, $param);
          }
          $acquia_cloud_client->addOption('json', [$param_name => $param]);
        }
      }
    }

    $path = $this->getRequestPath($input);
    $user_agent = sprintf("acli/%s", $this->getApplication()->getVersion());
    $acquia_cloud_client->addOption('headers', [
      'User-Agent' => $user_agent,
      'Accept'     => 'application/json',
    ]);

    try {
      $this->output->writeln([
        'Making API Request...',
        'method: ' . $this->method,
        'path: ' . $path,
        'query: ' . print_r($acquia_cloud_client->getQuery(), TRUE),
        'options: ' . print_r($acquia_cloud_client->getOptions(), TRUE),
      ], OutputInterface::VERBOSITY_DEBUG);
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
  public function addPostParameter($param_name, $value): void {
    $this->postParams[$param_name] = $value;
  }

  /**
   * @param $param_name
   */
  public function addQueryParameter($param_name, $value): void {
    $this->queryParams[$param_name] = $value;
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
  public function addPathParameter($param_name, $value): void {
    $this->pathParams[$param_name] = $value;
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
   * @param $param_spec
   * @param $value
   *
   * @return mixed
   */
  protected function castParamType($param_spec, $value) {
    // @todo File a CXAPI ticket regarding the inconsistent nesting of the 'type' property.
    if (array_key_exists('type', $param_spec)) {
      $type = $param_spec['type'];
    }
    elseif (array_key_exists('schema', $param_spec) && array_key_exists('type', $param_spec['schema'])) {
      $type = $param_spec['schema']['type'];
    }
    else {
      return $value;
    }

    switch ($type) {
      case 'int':
      case 'integer':
        $value = (int) $value;
        break;

      case 'bool':
      case 'boolean':
        $value = (bool) $value;
        break;
    }

    return $value;
  }

}
