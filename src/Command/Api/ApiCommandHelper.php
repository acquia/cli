<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\DataStore\YamlStore;
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
  protected $acliConfigFilepath;

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
   * @param string $acliConfigFilepath
   * @param string $repoRoot
   * @param \Acquia\Cli\Helpers\ClientService $cloudApiClientService
   * @param \AcquiaLogstream\LogstreamManager $logstreamManager
   * @param \Acquia\Cli\Helpers\SshHelper $sshHelper
   * @param string $sshDir
   */
  public function __construct(
    string $cloudConfigFilepath,
    LocalMachineHelper $localMachineHelper,
    JsonFileStore $datastoreCloud,
    YamlStore $datastoreAcli,
    TelemetryHelper $telemetryHelper,
    Amplitude $amplitude,
    string $acliConfigFilepath,
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
    $this->acliConfigFilepath = $acliConfigFilepath;
    $this->repoRoot = $repoRoot;
    $this->cloudApiClientService = $cloudApiClientService;
    $this->logstreamManager = $logstreamManager;
    $this->sshHelper = $sshHelper;
    $this->sshDir = $sshDir;
  }

  /**
   * @return \Symfony\Component\Cache\Adapter\PhpArrayAdapter
   */
  protected static function getCommandCache(): PhpArrayAdapter {
    $cache = new PhpArrayAdapter(__DIR__ . '/../../../var/cache/ApiCommands.cache', new FilesystemAdapter());
    return $cache;
  }

  /**
   * @return ApiCommandBase[]
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function getApiCommands(): array {
    $acquia_cloud_spec = $this->getCloudApiSpec();

    return $this->generateApiCommandsFromSpec($acquia_cloud_spec);
  }

  /**
   *
   */
  public function useCloudApiSpecCache(): bool {
    return !(getenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE') === '0');
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
   * @param string $usage
   *
   * @return string
   */
  protected function addOptionExampleToUsageForGetEndpoint($param_definition, string $usage): string {
    if (array_key_exists('example', $param_definition)) {
      $usage .= '--' . $param_definition['name'] . '="' . $param_definition['example'] . '" ';
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
        // @todo Remove ucfirst() and use actual key.
        $parameter_specification = $this->getParameterDefinitionFromSpec(ucfirst($parameter_definition->getName()), $acquia_cloud_spec);
        if ($parameter_specification['in'] === 'query') {
          $command->addQueryParameter($parameter_definition->getName(), $parameter_specification);
        }
        elseif($parameter_specification['in'] === 'path') {
          $command->addPathParameter($parameter_definition->getName(), $parameter_specification);
        }
      }
      $usage .= $query_param_usage_suffix;
      $input_definition = array_merge($input_definition, $query_input_definition);
    }

    // Parameters to be used in the request body.
    if (array_key_exists('requestBody', $schema)) {
      [$body_input_definition, $request_body_param_usage_suffix] = $this->addApiCommandParametersForRequestBody($schema, $acquia_cloud_spec);
      $request_body_schema = $this->getRequestBodyFromParameterSchema($schema, $acquia_cloud_spec);
      /** @var \Symfony\Component\Console\Input\InputOption|InputArgument $parameter_definition */
      foreach ($body_input_definition as $parameter_definition) {
        $parameter_specification = $this->getPropertySpecFromRequestBodyParam($request_body_schema, $parameter_definition);
        $command->addPostParameter($parameter_definition->getName(), $parameter_specification);
      }
      $usage .= $request_body_param_usage_suffix;
      $input_definition = array_merge($input_definition, $body_input_definition);
    }

    if (isset($input_definition)) {
      $command->setDefinition(new InputDefinition($input_definition));
      $command->addUsage(rtrim($usage));
      $this->addAliasUsageExamples($command, $input_definition, rtrim($usage));
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
    $request_body_schema = $this->getRequestBodyFromParameterSchema($schema, $acquia_cloud_spec);

    if (!array_key_exists('properties', $request_body_schema)) {
      return [];
    }
    foreach ($request_body_schema['properties'] as $prop_key => $param_definition) {
      $is_required = array_key_exists('required', $request_body_schema) && in_array($prop_key, $request_body_schema['required'], TRUE);
      $prop_key = self::renameParameter($prop_key);

      if ($is_required) {
        $input_definition[] = new InputArgument(
          $prop_key,
              $param_definition['type'] === 'array' ? InputArgument::IS_ARRAY | InputArgument::REQUIRED : InputArgument::REQUIRED,
              $param_definition['description']
          );
        $usage = $this->addPostArgumentUsageToExample($schema['requestBody'], $prop_key, $param_definition, 'argument', $usage);
      }
      else {
        $input_definition[] = new InputOption(
          $prop_key,
              NULL,
              $param_definition['type'] === 'array' ? InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED : InputOption::VALUE_REQUIRED,
              $param_definition['description']
                );
        $usage = $this->addPostArgumentUsageToExample($schema["requestBody"], $prop_key, $param_definition, 'option', $usage);
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
   * @param $prop_key
   * @param $param_definition
   * @param $type
   * @param $usage
   *
   * @return string
   */
  protected function addPostArgumentUsageToExample($request_body, $prop_key, $param_definition, $type, $usage): string {
    if (!array_key_exists('application/json', $request_body['content'])) {
      $request_body_schema = $request_body['content']['application/x-www-form-urlencoded'];
    }
    else {
      $request_body_schema = $request_body['content']['application/json'];
    }

    if (array_key_exists('example', $request_body_schema)) {
      $example = $request_body['content']['application/json']['example'];
      $prefix = $type === 'argument' ? '' : "--{$prop_key}=";
      if (array_key_exists($prop_key, $example)) {
        switch ($param_definition['type']) {
          case 'object':
            $usage .= $prefix . '"' . json_encode($example[$prop_key]) . '"" ';
            break;

          case 'array':
            $is_multidimensional = count($example[$prop_key]) !== count($example[$prop_key], COUNT_RECURSIVE);
            if (!$is_multidimensional) {
              foreach ($example[$prop_key] as $value) {
                $usage .= $prefix . "\"$value\" ";
              }
            }
            else {
              // @todo Pretty sure prevents the user from using the arguments.
              // Probably a bug. How can we allow users to specify a multidimensional array as an
              // argument?
              $value = json_encode($example[$prop_key]);
              $usage .= $prefix . "\"$value\" ";
            }
            break;

          case 'string':
          case 'boolean':
          case 'integer':
            if (is_array($example[$prop_key])) {
              $value = reset($example[$prop_key]);
            }
            else {
              $value = $example[$prop_key];
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
      $param_key = end($parts);
      $param_definition = $this->getParameterDefinitionFromSpec($param_key, $acquia_cloud_spec);
      $required = array_key_exists('required', $param_definition) && $param_definition['required'];
      $this->addAliasParameterDescriptions($param_definition);
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
        $usage = $this->addOptionExampleToUsageForGetEndpoint($param_definition, $usage);
      }
    }

    return [$input_definition, $usage];
  }

  /**
   * @param $param_key
   * @param $acquia_cloud_spec
   *
   * @return mixed
   */
  protected function getParameterDefinitionFromSpec($param_key, $acquia_cloud_spec) {
    return $acquia_cloud_spec['components']['parameters'][$param_key];
  }

  /**
   * @param $param_key
   * @param $acquia_cloud_spec
   *
   * @return mixed
   */
  protected function getParameterSchemaFromSpec($param_key, $acquia_cloud_spec) {
    return $acquia_cloud_spec['components']['schemas'][$param_key];
  }

  /**
   * @param \Symfony\Component\Cache\Adapter\PhpArrayAdapter $cache
   *
   * @param string $acquia_cloud_spec_file_checksum
   *
   * @return bool
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function isApiSpecChecksumCacheValid(PhpArrayAdapter $cache, $acquia_cloud_spec_file_checksum): bool {
    $api_spec_checksum_item = $cache->getItem('api_spec.checksum');
    // If there's an invalid entry OR there's no entry, return false.
    if (!$api_spec_checksum_item->isHit() || ($api_spec_checksum_item->isHit() && $api_spec_checksum_item->get() !== $acquia_cloud_spec_file_checksum)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @return mixed
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getCloudApiSpec(): array {
    // The acquia-spec.yaml is copied directly from the acquia/cx-api-spec repository. It can be updated
    // by running `composer update-cloud-api-spec`.
    $acquia_cloud_spec_file = __DIR__ . '/../../../assets/acquia-spec.yaml';
    $acquia_cloud_spec_file_checksum = md5_file($acquia_cloud_spec_file);
    $cache = self::getCommandCache();

    if (
      $this->useCloudApiSpecCache()
      && $this->isApiSpecChecksumCacheValid($cache, $acquia_cloud_spec_file_checksum)
    ) {
      $acquia_cloud_spec_yaml_item = $cache->getItem('api_spec.yaml');
      if ($acquia_cloud_spec_yaml_item && $acquia_cloud_spec_yaml_item->isHit()) {
        return $acquia_cloud_spec_yaml_item->get();
      }
    }

    // Parse file.
    $acquia_cloud_spec = Yaml::parseFile($acquia_cloud_spec_file);

    $cache->warmUp([
      'api_spec.yaml' => $acquia_cloud_spec,
      'api_spec.checksum' => $acquia_cloud_spec_file_checksum
    ]);

    return $acquia_cloud_spec;
  }

  /**
   * @param array $acquia_cloud_spec
   *
   * @return array
   */
  protected function generateApiCommandsFromSpec(array $acquia_cloud_spec): array {
    $api_commands = [];
    foreach ($acquia_cloud_spec['paths'] as $path => $endpoint) {
      // Skip internal endpoints. These shouldn't actually be in the spec.
      if (array_key_exists('x-internal', $endpoint) && $endpoint['x-internal']) {
        continue;
      }

      foreach ($endpoint as $method => $schema) {
        if (in_array($schema['x-cli-name'], $this->getSkippedApiCommands(), TRUE)) {
          continue;
        }

        // Remove errant '/api' prefix if is present.
        // @see https://github.com/acquia/cli/issues/240
        if (strpos($path, '/api') === 0) {
          $path = str_replace('/api', '', $path);
        }

        $command_name = 'api:' . $schema['x-cli-name'];
        $command = new ApiCommandBase($this->cloudConfigFilepath, $this->localMachineHelper, $this->datastoreCloud,
          $this->acliDatastore, $this->telemetryHelper, $this->amplitude, $this->acliConfigFilepath, $this->repoRoot,
          $this->cloudApiClientService, $this->logstreamManager, $this->sshHelper, $this->sshDir);
        $command->setName($command_name);
        $command->setDescription($schema['summary']);
        $command->setMethod($method);
        $command->setResponses($schema['responses']);
        $command->setServers($acquia_cloud_spec['servers']);
        $command->setPath($path);
        $this->addApiCommandParameters($schema, $acquia_cloud_spec, $command);
        $api_commands[] = $command;
      }
    }

    return $api_commands;
  }

  /**
   * @return array
   */
  protected function getSkippedApiCommands(): array {
    return [
      // Skip accounts:drush-aliases since we have remote:aliases:download instead and it actually returns
      // application/gzip content.
      'accounts:drush-aliases',
      // Skip any command that has a duplicative corresponding ACLI command.
      'ide:create',
      'ide:delete',
      'ide:list',
      'log:tail',
      'ssh-key:create',
      'ssh-key:create-upload',
      'ssh-key:delete',
      'ssh-key:list',
      'ssh-key:upload',
      // Skip buggy or unsupported endpoints.
      'environments:stack-metrics-data-metric',
    ];
  }

  /**
   * @param \Acquia\Cli\Command\Api\ApiCommandBase $command
   * @param array $input_definition
   * @param string $usage
   */
  protected function addAliasUsageExamples(ApiCommandBase $command, array $input_definition, string $usage): void {
    foreach ($input_definition as $key => $parameter) {
      if ($parameter->getName() === 'applicationUuid') {
        $usage_parts = explode(' ', $usage);
        $usage_parts[$key] = "myapp";
        $usage = implode(' ', $usage_parts);
        $command->addUsage($usage);
      }
      if ($parameter->getName() === 'environmentId') {
        $usage_parts = explode(' ', $usage);
        $usage_parts[$key] = "myapp.dev";
        $usage = implode(' ', $usage_parts);
        $command->addUsage($usage);
      }
    }
  }

  /**
   * @param $param_definition
   */
  protected function addAliasParameterDescriptions(&$param_definition): void {
    if ($param_definition['name'] === 'applicationUuid') {
      $param_definition['description'] .= ' You may also use an application alias or omit the argument if you run the command in a linked directory.';
    }
    if ($param_definition['name'] === 'environmentId') {
      $param_definition['description'] .= " You may also use an environment alias or UUID.";
    }
  }

  /**
   * @param $schema
   * @param $acquia_cloud_spec
   *
   * @return array
   */
  protected function getRequestBodyFromParameterSchema($schema, $acquia_cloud_spec): array {
    if (!array_key_exists('application/json', $schema['requestBody']['content'])) {
      $request_body_schema = $schema['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
    }
    else {
      $request_body_schema = $schema['requestBody']['content']['application/json']['schema'];
    }

    // If this is a reference to the top level schema, go grab the referenced component.
    if (array_key_exists('$ref', $request_body_schema)) {
      $parts = explode('/', $request_body_schema['$ref']);
      $param_key = end($parts);
      $request_body_schema = $this->getParameterSchemaFromSpec($param_key, $acquia_cloud_spec);
    }

    return $request_body_schema;
  }

  /**
   * @param array $request_body_schema
   * @param $parameter_definition
   *
   * @return mixed
   */
  protected function getPropertySpecFromRequestBodyParam(array $request_body_schema, $parameter_definition) {
    if (array_key_exists($parameter_definition->getName(), $request_body_schema['properties'])) {
     return $request_body_schema['properties'][$parameter_definition->getName()];
    }

    return NULL;
  }

  /*
   * @return array
   */
  protected static function getParameterRenameMap(): array {
    // Format should be ['original => new'].
    return [
      // @see api:environments:update.
      'version' => 'lang_version',
      // @see api:environments:cron-create
      'command' => 'cron_command',
    ];
  }

  /**
   * @param $prop_key
   *
   * @return mixed
   */
  public static function renameParameter($prop_key) {
    $parameter_rename_map = self::getParameterRenameMap();
    if (array_key_exists($prop_key, $parameter_rename_map)) {
      $prop_key = $parameter_rename_map[$prop_key];
    }
    return $prop_key;
  }

  /**
   * @param $prop_key
   *
   * @return mixed
   */
  public static function restoreRenamedParameter($prop_key) {
    $parameter_rename_map = array_flip(self::getParameterRenameMap());
    if (array_key_exists($prop_key, $parameter_rename_map)) {
      $prop_key = $parameter_rename_map[$prop_key];
    }
    return $prop_key;
  }

}
