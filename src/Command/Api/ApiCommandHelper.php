<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use AcquiaLogstream\LogstreamManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Webmozart\KeyValueStore\JsonFileStore;
use Zumba\Amplitude\Amplitude;

/**
 *
 */
class ApiCommandHelper {

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * @var \Symfony\Component\Console\Helper\FormatterHelper*/
  protected $formatter;

  /**
   * @var \Acquia\Cli\Helpers\TelemetryHelper
   */
  protected $telemetryHelper;

  /**
   * @var LocalMachineHelper
   */
  public $localMachineHelper;

  /**
   * @var JsonFileStore
   */
  protected $datastoreCloud;

  /**
   * @var \Webmozart\KeyValueStore\JsonFileStore
   */
  protected $acliDatastore;

  /**
   * @var string
   */
  protected $cloudConfigFilepath;

  /**
   * @var string
   */
  protected $acliConfigFilename;

  /**
   * @var \Zumba\Amplitude\Amplitude
   */
  protected $amplitude;

  protected $repoRoot;

  /**
   * @var \Acquia\Cli\Helpers\ClientService
   */
  protected $cloudApiClientService;

  /**
   * @var \AcquiaLogstream\LogstreamManager
   */
  protected $logstreamManager;

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  public $sshHelper;

  /**
   * @var string
   */
  protected $sshDir;

  /**
   * CommandBase constructor.
   *
   * @param string $cloudConfigFilepath
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $localMachineHelper
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastoreCloud
   * @param \Webmozart\KeyValueStore\JsonFileStore $datastoreAcli
   * @param \Acquia\Cli\Helpers\TelemetryHelper $telemetryHelper
   * @param \Zumba\Amplitude\Amplitude $amplitude
   * @param string $acliConfigFilename
   * @param string $repoRoot
   */
  public function __construct(
    string $cloudConfigFilepath,
    LocalMachineHelper $localMachineHelper,
    JsonFileStore $datastoreCloud,
    JsonFileStore $datastoreAcli,
    TelemetryHelper $telemetryHelper,
    Amplitude $amplitude,
    string $acliConfigFilename,
    string $repoRoot,
    ClientService $cloudApiClientService,
    LogstreamManager $logstreamManager,
    SshHelper $sshHelper,
    string $sshDir
  ) {
    $this->cloudConfigFilepath = $cloudConfigFilepath;
    $this->localMachineHelper = $localMachineHelper;
    $this->datastoreCloud = $datastoreCloud;
    $this->acliDatastore = $datastoreAcli;
    $this->telemetryHelper = $telemetryHelper;
    $this->amplitude = $amplitude;
    $this->acliConfigFilename = $acliConfigFilename;
    $this->repoRoot = $repoRoot;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
  }

  /**
   * @return ApiCommandBase[]
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function getApiCommands(): array {
    // The acquia-spec.yaml is copied directly from the acquia/cx-api-spec repository. It can be updated
    // by running `composer update-cloud-api-spec`.
    $acquia_cloud_spec_file = __DIR__ . '/../../../assets/acquia-spec.yaml';
    $acquia_cloud_spec_file_checksum = md5_file($acquia_cloud_spec_file);

    $cache = new PhpArrayAdapter(__DIR__ . '/../../../cache/ApiCommands.cache', new FilesystemAdapter());

    // Check to see if the API spec has changed since we cached commands.
    $is_command_cache_valid = $this->useCommandCache() && $this->isCommandCacheValid($cache, $acquia_cloud_spec_file_checksum);
    $api_commands_cache_item = $cache->getItem('commands.api');
    if ($is_command_cache_valid && $api_commands_cache_item->isHit()) {
      return $api_commands_cache_item->get();
    }

    $acquia_cloud_spec = Yaml::parseFile($acquia_cloud_spec_file);
    $api_commands = [];
    foreach ($acquia_cloud_spec['paths'] as $path => $endpoint) {
      // Skip internal endpoints. These shouldn't actually be in the spec.
      if (array_key_exists('x-internal', $endpoint) && $endpoint['x-internal']) {
        continue;
      }

      foreach ($endpoint as $method => $schema) {
        // Skip accounts:drush-aliases since we have remote:aliases:download instead and it actually returns
        // application/gzip content.
        if ($schema['x-cli-name'] === 'accounts:drush-aliases') {
          continue;
        }

        $command_name = 'api:' . $schema['x-cli-name'];
        $command = new ApiCommandBase(
          $this->cloudConfigFilepath,
     $this->localMachineHelper,
     $this->datastoreCloud,
     $this->acliDatastore,
     $this->telemetryHelper,
     $this->amplitude,
     $this->acliConfigFilename,
     $this->repoRoot,
     $this->cloudApiClientService,
     $this->logstreamManager,
     $this->sshHelper,
     $this->sshDir);
        $command->setName($command_name);
        $command->setDescription($schema['summary']);
        $command->setMethod($method);
        $command->setResponses($schema['responses']);
        $command->setServers($acquia_cloud_spec['servers']);
        $command->setPath($path);
        // This is unhidden when `acli api:list` is run.
        // @todo This breaks console's ability to help with "did you mean?" for command typos!
        // Consider hiding ONLY when the `list` command is being executed.
        $command->setHidden(TRUE);
        $this->addApiCommandParameters($schema, $acquia_cloud_spec, $command);
        $api_commands[] = $command;
      }
    }

    // Save the API spec file checksum and api commands to the cache.
    $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
    $api_spec_checksum_item->set($acquia_cloud_spec_file_checksum);
    $cache->save($api_spec_checksum_item);
    $api_commands_cache_item->set($api_commands);
    $cache->save($api_commands_cache_item);

    return $api_commands;
  }

  /**
   *
   */
  public function useCommandCache(): bool {
    if (getenv('ACQUIA_CLI_USE_COMMAND_CACHE') === '0') {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param $param_definition
   * @param string $usage
   *
   * @return mixed|string
   */
  protected function addArgumentExampleToUsageForGetEndpoint($param_definition, string $usage) {
    if (array_key_exists('example', $param_definition)) {
      if (is_array($param_definition['example'])) {
        $usage = reset($param_definition['example']);
      }
      elseif (strpos($param_definition['example'], ' ') !== FALSE) {
        $usage .= '"' . $param_definition['example'] . '" ';
      }
      else {
        $usage .= $param_definition['example'] . ' ';
      }
    }

    return $usage;
  }

  /**
   * @param $param_definition
   * @param $param_name
   * @param string $usage
   *
   * @return string
   */
  protected function addOptionExampleToUsageForGetEndpoint($param_definition, $param_name, string $usage): string {
    if (array_key_exists('example', $param_definition)) {
      $usage .= '--' . strtolower($param_name) . '="' . $param_definition['example'] . '" ';
    }

    return $usage;
  }

  /**
   * @param $schema
   * @param $acquia_cloud_spec
   * @param \Acquia\Cli\Command\Api\ApiCommandBase $command
   */
  protected function addApiCommandParameters($schema, $acquia_cloud_spec, ApiCommandBase $command): void {
    $input_definition = [];
    $usage = '';
    // Parameters to be used in the request query and path.
    if (array_key_exists('parameters', $schema)) {
      [$query_input_definition, $query_param_usage_suffix] = $this->addApiCommandParametersForPathAndQuery($schema, $acquia_cloud_spec);
      /** @var \Symfony\Component\Console\Input\InputOption|InputArgument $parameter_definition */
      foreach ($query_input_definition as $parameter_definition) {
        $token = '{' . $parameter_definition->getName() . '}';
        if (strpos($command->getPath(), $token) !== FALSE) {
           $command->addPathParameter($parameter_definition->getName());
        }
        else {
          $command->addQueryParameter($parameter_definition->getName());
        }
      }
      $usage .= $query_param_usage_suffix;
      $input_definition += $query_input_definition;
    }

    // Parameters to be used in the request body.
    if (array_key_exists('requestBody', $schema)) {
      [$body_input_definition, $request_body_param_usage_suffix] = $this->addApiCommandParametersForRequestBody($schema, $acquia_cloud_spec);
      /** @var \Symfony\Component\Console\Input\InputOption|InputArgument $parameter_definition */
      foreach ($body_input_definition as $parameter_definition) {
        $command->addPostParameter($parameter_definition->getName());
      }
      $usage .= $request_body_param_usage_suffix;
      $input_definition += $body_input_definition;
    }

    if (isset($input_definition)) {
      $command->setDefinition(new InputDefinition($input_definition));
      $command->addUsage($usage);
    }
  }

  /**
   * @param $schema
   * @param $acquia_cloud_spec
   *
   * @return array
   */
  protected function addApiCommandParametersForRequestBody($schema, $acquia_cloud_spec): array {
    $usage = '';
    $input_definition = [];
    if (!array_key_exists('application/json', $schema['requestBody']['content'])) {
      $request_body_schema = $schema['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
    }
    else {
      $request_body_schema = $schema['requestBody']['content']['application/json']['schema'];
    }

    // If this is a reference to the top level schema, go grab the referenced component.
    if (array_key_exists('$ref', $request_body_schema)) {
      $parts = explode('/', $request_body_schema['$ref']);
      $param_name = end($parts);
      $request_body_schema = $this->getParameterSchemaFromSpec($param_name, $acquia_cloud_spec);
    }

    if (!array_key_exists('properties', $request_body_schema)) {
      return [];
    }
    foreach ($request_body_schema['properties'] as $param_name => $param_definition) {
      $is_required = array_key_exists('required', $request_body_schema) && in_array($param_name, $request_body_schema['required'], TRUE);
      if ($is_required) {
        $input_definition[] = new InputArgument(
              $param_name,
              $param_definition['type'] === 'array' ? InputArgument::IS_ARRAY | InputArgument::REQUIRED : InputArgument::REQUIRED,
              $param_definition['description']
          );
        $usage = $this->addPostArgumentUsageToExample($schema["requestBody"], $param_name, $param_definition, 'argument', $usage);
      }
      else {
        $input_definition[] = new InputOption(
              $param_name,
              NULL,
              $param_definition['type'] === 'array' ? InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED : InputOption::VALUE_REQUIRED,
              $param_definition['description']
                );
        $usage = $this->addPostArgumentUsageToExample($schema["requestBody"], $param_name, $param_definition, 'option', $usage);
        // @todo Add validator for $param['enum'] values?
      }
    }
    /** @var \Symfony\Component\Console\Input\InputArgument|InputOption $parameter_definition */
    foreach ($input_definition as $index => $parameter_definition) {
      if ($parameter_definition->isArray()) {
        // Move to the end of the array.
        unset($input_definition[$index]);
        $input_definition[] = $parameter_definition;
      }
    }

    return [$input_definition, $usage];
  }

  /**
   * @param $request_body
   * @param $param_name
   * @param $param_definition
   * @param $type
   * @param $usage
   *
   * @return string
   */
  protected function addPostArgumentUsageToExample($request_body, $param_name, $param_definition, $type, $usage): string {
    if (!array_key_exists('application/json', $request_body['content'])) {
      $request_body_schema = $request_body['content']['application/x-www-form-urlencoded'];
    }
    else {
      $request_body_schema = $request_body['content']['application/json'];
    }

    if (array_key_exists('example', $request_body_schema)) {
      $example = $request_body['content']['application/json']['example'];
      $prefix = $type === 'argument' ? '' : strtolower("--{$param_name}=");
      if (array_key_exists($param_name, $example)) {
        switch ($param_definition['type']) {
          case 'object':
            $usage .= $prefix . '"' . json_encode($example[$param_name]) . '"" ';
            break;

          case 'array':
            $is_multidimensional = count($example[$param_name]) !== count($example[$param_name], COUNT_RECURSIVE);
            if (!$is_multidimensional) {
              $value = implode(',', $example[$param_name]);
            }
            else {
              // @todo Pretty sure prevents the user from using the arguments.
              // Probably a bug. How can we allow users to specify a multidimensional array as an
              // argument?
              $value = json_encode($example[$param_name]);
            }
            $usage .= $prefix . "\"$value\" ";
            break;

          case 'string':
          case 'boolean':
          case 'integer':
            if (is_array($example[$param_name])) {
              $value = reset($example[$param_name]);
            }
            else {
              $value = $example[$param_name];
            }
            $usage .= $prefix . "\"{$value}\" ";
            break;
        }
      }
    }
    return $usage;
  }

  /**
   * @param $schema
   * @param $acquia_cloud_spec
   *
   * @return array
   */
  protected function addApiCommandParametersForPathAndQuery($schema, $acquia_cloud_spec): array {
    $usage = '';
    $input_definition = [];
    foreach ($schema['parameters'] as $parameter) {
      $parts = explode('/', $parameter['$ref']);
      $param_name = end($parts);
      $param_definition = $this->getParameterDefinitionFromSpec($param_name, $acquia_cloud_spec);
      $required = array_key_exists('required', $param_definition) && $param_definition['required'];
      if ($required) {
        $input_definition[] = new InputArgument(
              $param_definition['name'],
              InputArgument::REQUIRED,
              $param_definition['description']
          );
        $usage = $this->addArgumentExampleToUsageForGetEndpoint($param_definition, $usage);
      }
      else {
        $input_definition[] = new InputOption(
              $param_definition['name'],
              NULL,
              InputOption::VALUE_REQUIRED,
              $param_definition['description']
                );
        $usage = $this->addOptionExampleToUsageForGetEndpoint($param_definition, $param_name, $usage);
      }
    }

    return [$input_definition, $usage];
  }

  /**
   * @param $param_name
   * @param $acquia_cloud_spec
   *
   * @return mixed
   */
  protected function getParameterDefinitionFromSpec($param_name, $acquia_cloud_spec) {
    return $acquia_cloud_spec['components']['parameters'][$param_name];
  }

  /**
   * @param $param_name
   * @param $acquia_cloud_spec
   * @return mixed
   */
  protected function getParameterSchemaFromSpec($param_name, $acquia_cloud_spec) {
    return $acquia_cloud_spec['components']['schemas'][$param_name];
  }

  /**
   * @param \Symfony\Component\Cache\Adapter\PhpArrayAdapter $cache
   *
   * @param string $acquia_cloud_spec_file_checksum
   *
   * @return bool
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function isCommandCacheValid(PhpArrayAdapter $cache, $acquia_cloud_spec_file_checksum): bool {
    $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
    // If there's an invalid entry OR there's no entry, return false.
    if (!$api_spec_checksum_item->isHit() || ($api_spec_checksum_item->isHit() && $api_spec_checksum_item->get() !== $acquia_cloud_spec_file_checksum)) {
      return FALSE;
    }

    return TRUE;
  }

}
