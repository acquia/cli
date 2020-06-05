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
   * @var string*/
  protected $method;

  /**
   * @var array*/
  protected $responses;

  /**
   * @var array*/
  protected $servers;

  /**
   * @var string*/
  protected $path;
  /**
   * @var array*/
  private $queryParams = [];
  /**
   * @var array*/
  private $postParams = [];

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
        if ($input->hasOption($key) && $input->getOption($key) !== NULL) {
          $acquia_cloud_client->addQuery($key, $input->getOption($key));
        }
      }
    }
    if ($this->postParams) {
      foreach ($this->postParams as $param_name) {
        $acquia_cloud_client->addOption('form_params', [$param_name => $input->getArgument($param_name)]);
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
  public function addQueryParameter($param_name) {
    $this->queryParams[] = $param_name;
  }

}
