<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
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
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);

    if ($input->hasArgument('applicationUuid') && !$input->getArgument('applicationUuid')) {
      $output->writeln('Inferring Cloud Application UUID for this command since none was provided...');
      $application_uuid = $this->determineCloudApplication();
      $input->setArgument('applicationUuid', $application_uuid);
    }
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
      }
    }
    if ($this->postParams) {
      foreach ($this->postParams as $param_name) {
        $param = $this->getParamFromInput($input, $param_name);
        $acquia_cloud_client->addOption('form_params', [$param_name => $param]);
      }
    }

    $path = $this->getRequestPath($input);
    $response = $acquia_cloud_client->request($this->method, $path);
    // @todo Add syntax highlighting to json output.
    $contents = json_encode($response, JSON_PRETTY_PRINT);
    $this->output->writeln($contents);

    return 0;
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
        $path = str_replace($token, $value, $this->path);
      }
    }

    return $path;
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

}
