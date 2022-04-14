<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\CommandFactoryInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Yaml\Yaml;

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
   * @var ConsoleLogger
   */
  private $logger;

  /**
   * CommandBase constructor.
   *
   * @param ConsoleLogger $logger
   */
  public function __construct(
    ConsoleLogger $logger
  ) {
    $this->logger = $logger;
  }

  /**
   * @param string $acquia_cloud_spec_file_path
   * @param string $command_prefix
   * @param \Acquia\Cli\CommandFactoryInterface $command_factory
   *
   * @return array
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function getApiCommands(string $acquia_cloud_spec_file_path, string $command_prefix, CommandFactoryInterface $command_factory): array {
    $acquia_cloud_spec = $this->getCloudApiSpec($acquia_cloud_spec_file_path);
    $api_commands = $this->generateApiCommandsFromSpec($acquia_cloud_spec, $command_prefix, $command_factory);
    $api_list_commands = $this->generateApiListCommands($api_commands, $command_prefix, $command_factory);
    return array_merge($api_commands, $api_list_commands);
  }

  /**
   *
   */
  public function useCloudApiSpecCache(): bool {
    return !(getenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE') === '0');
  }

  /**
   * @param array $param_definition
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
   * @param array $param_definition
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
   * @param array $schema
   * @param array $acquia_cloud_spec
   * @param CommandBase $command
   */
  protected function addApiCommandParameters($schema, $acquia_cloud_spec, CommandBase $command): void {
    $input_definition = [];
    $usage = '';

    // Parameters to be used in the request query and path.
    if (array_key_exists('parameters', $schema)) {
      [$query_input_definition, $query_param_usage_suffix] = $this->addApiCommandParametersForPathAndQuery($schema, $acquia_cloud_spec);
      /** @var \Symfony\Component\Console\Input\InputOption|InputArgument $parameter_definition */
      foreach ($query_input_definition as $parameter_definition) {
        $parameter_specification = $this->getParameterDefinitionFromSpec($parameter_definition->getName(), $acquia_cloud_spec, $schema);
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

    $command->setDefinition(new InputDefinition($input_definition));
    if ($usage) {
      $command->addUsage(rtrim($usage));
    }
    $this->addAliasUsageExamples($command, $input_definition, rtrim($usage));
  }

  /**
   * @param array $schema
   * @param array $acquia_cloud_spec
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
              array_key_exists('description', $param_definition) ? $param_definition['description'] : $prop_key
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
    $request_body_schema = [];
    if (array_key_exists('application/x-www-form-urlencoded', $request_body['content'])) {
      $request_body_schema = $request_body['content']['application/x-www-form-urlencoded'];
    }
    elseif (array_key_exists('application/json', $request_body['content'])) {
      $request_body_schema = $request_body['content']['application/json'];
    }
    elseif (array_key_exists('multipart/form-data', $request_body['content'])) {
      $request_body_schema = $request_body['content']['multipart/form-data'];
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
   * @param array $schema
   * @param array $acquia_cloud_spec
   *
   * @return array
   */
  protected function addApiCommandParametersForPathAndQuery(array $schema, array $acquia_cloud_spec): array {
    $usage = '';
    $input_definition = [];
    if (!array_key_exists('parameters', $schema)) {
      return [];
    }
    foreach ($schema['parameters'] as $parameter) {
      if (array_key_exists('$ref', $parameter)) {
        $parts = explode('/', $parameter['$ref']);
        $param_key = end($parts);
        $param_definition = $this->getParameterDefinitionFromSpec($param_key, $acquia_cloud_spec, $schema);
      }
      else {
        $param_definition = $parameter;
      }

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
   * @param string $param_key
   * @param array $acquia_cloud_spec
   *
   * @return mixed
   */
  protected function getParameterDefinitionFromSpec($param_key, $acquia_cloud_spec, $schema) {
    $uppercase_key = ucfirst($param_key);
    if (array_key_exists('parameters', $acquia_cloud_spec['components'])
      && array_key_exists($uppercase_key, $acquia_cloud_spec['components']['parameters'])) {
      return $acquia_cloud_spec['components']['parameters'][$uppercase_key];
    }
    foreach ($schema['parameters'] as $parameter) {
      if ($parameter['name'] === $param_key) {
        return $parameter;
      }
    }
    return NULL;
  }

  /**
   * @param string $param_key
   * @param array $acquia_cloud_spec
   *
   * @return mixed
   */
  protected function getParameterSchemaFromSpec(string $param_key, array $acquia_cloud_spec) {
    return $acquia_cloud_spec['components']['schemas'][$param_key];
  }

  /**
   * @param \Symfony\Component\Cache\Adapter\PhpArrayAdapter $cache
   * @param string $acquia_cloud_spec_file_path
   * @param string $acquia_cloud_spec_file_checksum
   *
   * @return bool
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function isApiSpecChecksumCacheValid($cache_item, string $acquia_cloud_spec_file_checksum): bool {
    // If the spec file doesn't exist, assume cache is valid.
    if ($cache_item->isHit() && !$acquia_cloud_spec_file_checksum) {
      return TRUE;
    }
    // If there's an invalid entry OR there's no entry, return false.
    if (!$cache_item->isHit() || ($cache_item->isHit() && $cache_item->get() !== $acquia_cloud_spec_file_checksum)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param string $spec_file_path
   *
   * @return array
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getCloudApiSpec($spec_file_path): array {
    $cache_key = basename($spec_file_path);
    $cache = new PhpArrayAdapter(__DIR__ . '/../../../var/cache/' . $cache_key . '.cache', new NullAdapter());
    $checksum = md5_file($spec_file_path);
    $cache_item_checksum = $cache->getItem($cache_key . '.checksum');
    $cache_item_spec = $cache->getItem($cache_key);

    // When running the phar, the original file may not exist. In that case, always use the cache.
    if (!file_exists($spec_file_path)) {
      if ($cache_item_spec->isHit()) {
        return $cache_item_spec->get();
      }
    }

    // Otherwise, only use cache when it is valid.
    if ($this->useCloudApiSpecCache()
      && $this->isApiSpecChecksumCacheValid($cache_item_checksum, $checksum)
    ) {
      if ($cache_item_spec->isHit()) {
        return $cache_item_spec->get();
      }
    }

    // Parse file. This can take a long while!
    $this->logger->debug("Rebuilding caches...");
    $spec = Yaml::parseFile($spec_file_path);

    $cache->warmUp([
      $cache_key => $spec,
      $cache_key . '.checksum' => $checksum,
    ]);

    return $spec;
  }

  /**
   * @param array $acquia_cloud_spec
   * @param string $command_prefix
   * @param \Acquia\Cli\CommandFactoryInterface $command_factory
   *
   * @return ApiBaseCommand[]
   */
  protected function generateApiCommandsFromSpec(array $acquia_cloud_spec, string $command_prefix, CommandFactoryInterface $command_factory): array {
    $api_commands = [];
    foreach ($acquia_cloud_spec['paths'] as $path => $endpoint) {
      // Skip internal endpoints. These shouldn't actually be in the spec.
      if (array_key_exists('x-internal', $endpoint) && $endpoint['x-internal']) {
        continue;
      }

      foreach ($endpoint as $method => $schema) {
        if (!array_key_exists('x-cli-name', $schema)) {
          continue;
        }

        if (in_array($schema['x-cli-name'], $this->getSkippedApiCommands(), TRUE)) {
          continue;
        }

        // Skip deprecated endpoints.
        if (array_key_exists('deprecated', $schema) && $schema['deprecated']) {
          continue;
        }

        $command_name = $command_prefix . ':' . $schema['x-cli-name'];
        $command = $command_factory->createCommand();
        $command->setName($command_name);
        $command->setDescription($schema['summary']);
        $command->setMethod($method);
        $command->setResponses($schema['responses']);
        if (array_key_exists('servers', $acquia_cloud_spec)) {
          $command->setServers($acquia_cloud_spec['servers']);
        }
        $command->setPath($path);
        $command->setHelp("For more help, see https://cloudapi-docs.acquia.com/");
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
   * @param \Acquia\Cli\Command\Api\ApiBaseCommand $command
   * @param array $input_definition
   * @param string $usage
   */
  protected function addAliasUsageExamples(ApiBaseCommand $command, array $input_definition, string $usage): void {
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
   * @param array $schema
   * @param $acquia_cloud_spec
   *
   * @return array
   */
  protected function getRequestBodyFromParameterSchema($schema, $acquia_cloud_spec): array {
    $request_body_schema = [];
    if (array_key_exists('application/json', $schema['requestBody']['content'])) {
      $request_body_schema = $schema['requestBody']['content']['application/json']['schema'];
    }
    elseif (array_key_exists('application/x-www-form-urlencoded', $schema['requestBody']['content'])) {
      $request_body_schema = $schema['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
    }
    elseif (array_key_exists('multipart/form-data', $schema['requestBody']['content'])) {
      $request_body_schema = $schema['requestBody']['content']['multipart/form-data']['schema'];
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

  /**
   * @param array $api_commands
   *
   * @return ApiListCommandBase[]
   */
  protected function generateApiListCommands(array $api_commands, $command_prefix, CommandFactoryInterface $command_factory): array {
    $api_list_commands = [];
    foreach ($api_commands as $api_command) {
      $command_name_parts = explode(':', $api_command->getName());
      if (count($command_name_parts) < 3) {
        continue;
      }
      $namespace = $command_name_parts[1];
      if (!array_key_exists($namespace, $api_list_commands)) {
        $command = $command_factory->createListCommand();
        $name = $command_prefix . ':' . $namespace;
        $command->setName($name);
        $command->setNamespace($name);
        $command->setDescription("List all API commands for the {$namespace} resource");
        $api_list_commands[$name] = $command;
      }
    }
    return $api_list_commands;
  }

}
