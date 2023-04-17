<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use Closure;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

class ApiBaseCommand extends CommandBase {

  protected static $defaultName = 'api:base';

  protected string $method;

  /**
   * @var array
   */
  protected array $responses;

  /**
   * @var array
   */
  protected array $servers;

  protected string $path;

  /**
   * @var array
   */
  private array $queryParams = [];

  /**
   * @var array
   */
  private array $postParams = [];

  /** @var array  */
  private array $pathParams = [];

  protected function configure(): void {
    $this->setHidden(TRUE);
    parent::configure();
  }

  protected function interact(InputInterface $input, OutputInterface $output): void {
    $params = array_merge($this->queryParams, $this->postParams, $this->pathParams);
    foreach ($this->getDefinition()->getArguments() as $argument) {
      if ($argument->isRequired() && !$input->getArgument($argument->getName())) {
        $this->io->note([
          "{$argument->getName()} is a required argument.",
          $argument->getDescription(),
        ]);
        // Choice question.
        if (array_key_exists($argument->getName(), $params)
          && array_key_exists('schema', $params[$argument->getName()])
          && array_key_exists('enum', $params[$argument->getName()]['schema'])) {
          $choices = $params[$argument->getName()]['schema']['enum'];
          $answer = $this->io->choice("Select a value for {$argument->getName()}", $choices, $argument->getDefault());
        }
        elseif (array_key_exists($argument->getName(), $params)
          && array_key_exists('type', $params[$argument->getName()])
          && $params[$argument->getName()]['type'] === 'boolean') {
          $answer = $this->io->choice("Select a value for {$argument->getName()}", ['false', 'true'], $argument->getDefault());
          $answer = $answer === 'true';
        }
        // Free form.
        else {
          $answer = $this->askFreeFormQuestion($argument, $params);
        }
        $input->setArgument($argument->getName(), $answer);
      }
    }
    parent::interact($input, $output);
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Build query from non-null options.
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $this->addQueryParamsToClient($input, $acquia_cloud_client);
    $this->addPostParamsToClient($input, $acquia_cloud_client);
    $acquia_cloud_client->addOption('headers', [
      'Accept' => 'application/json',
    ]);

    try {
      if ($this->output->isVeryVerbose()) {
        $acquia_cloud_client->addOption('debug', $this->output);
      }
      $path = $this->getRequestPath($input);
      $response = $acquia_cloud_client->request($this->method, $path);
      $exit_code = 0;
    }
    catch (ApiErrorException $exception) {
      $response = $exception->getResponseBody();
      $exit_code = 1;
    }

    $contents = json_encode($response, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $this->output->writeln($contents);

    return $exit_code;
  }

  public function setMethod(string $method): void {
    $this->method = $method;
  }

  /**
   * @param array $responses
   */
  public function setResponses(array $responses): void {
    $this->responses = $responses;
  }

  /**
   * @param array $servers
   */
  public function setServers(array $servers): void {
    $this->servers = $servers;
  }

  public function setPath(string $path): void {
    $this->path = $path;
  }

  protected function getRequestPath(InputInterface $input): string {
    $path = $this->path;

    $arguments = $input->getArguments();
    // The command itself is the first argument. Remove it.
    array_shift($arguments);
    foreach ($arguments as $key => $value) {
      $token = '{' . $key . '}';
      if (str_contains($path, $token)) {
        $path = str_replace($token, $value, $path);
      }
    }

    return $path;
  }

  public function getMethod(): string {
    return $this->method;
  }

  /**
   * @param $param_name
   * @param $value
   */
  public function addPostParameter($param_name, $value): void {
    $this->postParams[$param_name] = $value;
  }

  /**
   * @param $param_name
   * @param $value
   */
  public function addQueryParameter($param_name, $value): void {
    $this->queryParams[$param_name] = $value;
  }

  public function getPath(): string {
    return $this->path;
  }

  /**
   * @param $value
   */
  public function addPathParameter(string $param_name, $value): void {
    $this->pathParams[$param_name] = $value;
  }

  /**
   * @return bool|string|string[]|null
   */
  private function getParamFromInput(InputInterface $input, string $param_name): array|bool|string|null {
    if ($input->hasArgument($param_name)) {
      return $input->getArgument($param_name);
    }

    if ($input->hasParameterOption('--' . $param_name)) {
      return $input->getOption($param_name);
    }
    return NULL;
  }

  /**
   * @param array $param_spec
   */
  private function castParamType(array $param_spec, array|string $value): array|bool|int|string {
    $one_of = $this->getParamTypeOneOf($param_spec);
    if (isset($one_of)) {
      $types = [];
      foreach ($one_of as $type) {
        if ($type['type'] === 'array' && str_contains($value, ',')) {
          return $this->castParamToArray($type, $value);
        }
        $types[] = $type['type'];
      }
      if ((in_array('integer', $types, TRUE) || in_array('int', $types, TRUE))
        && ctype_digit($value)) {
        return $this->doCastParamType('integer', $value);
      }
    }
    elseif ($param_spec['type'] === 'array') {
      if (count($value) === 1) {
        return $this->castParamToArray($param_spec, $value[0]);
      }

      return $this->castParamToArray($param_spec, $value);
    }

    $type = $this->getParamType($param_spec);
    if (!$type) {
      return $value;
    }

    return $this->doCastParamType($type, $value);
  }

  private function doCastParamType(string $type, mixed $value): array|bool|int|string {
    return match ($type) {
      'int', 'integer' => (int) $value,
      'bool', 'boolean' => $this->castBool($value),
      'array' => is_string($value) ? explode(',', $value) : (array) $value,
      'string' => (string) $value,
      'mixed' => $value,
    };
  }

  /**
   * @param $val
   */
  public function castBool($val): bool {
    return (bool) (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : $val);
  }

  /**
   * @param array $param_spec
   */
  private function getParamType(array $param_spec): ?string {
    // @todo File a CXAPI ticket regarding the inconsistent nesting of the 'type' property.
    if (array_key_exists('type', $param_spec)) {
      return $param_spec['type'];
    }

    if (array_key_exists('schema', $param_spec) && array_key_exists('type', $param_spec['schema'])) {
      return $param_spec['schema']['type'];
    }
    return NULL;
  }

  /**
   * @param array $params
   */
  private function createCallableValidator(InputArgument $argument, array $params): ?callable {
    $validator = NULL;
    if (array_key_exists($argument->getName(), $params)) {
      $param_spec = $params[$argument->getName()];
      $constraints = [
        new NotBlank(),
      ];
      if ($type = $this->getParamType($param_spec)) {
        if (in_array($type, ['int', 'integer'])) {
          // Need to evaluate whether a string contains only digits.
          $constraints[] = new Type('digit');
        }
        elseif ($type === 'array') {
          $constraints[] = new Type('string');
        }
        else {
          $constraints[] = new Type($type);
        }
      }
      if (array_key_exists('schema', $param_spec)) {
        $schema = $param_spec['schema'];
        $constraints = $this->createLengthConstraint($schema, $constraints);
        $constraints = $this->createRegexConstraint($schema, $constraints);
      }
      $validator = $this->createValidatorFromConstraints($constraints);
    }
    return $validator;
  }

  /**
   * @param array $schema
   * @param array $constraints
   * @return array
   */
  private function createLengthConstraint(array $schema, array $constraints): array {
    if (array_key_exists('minLength', $schema) || array_key_exists('maxLength', $schema)) {
      $length_options = [];
      if (array_key_exists('minLength', $schema)) {
        $length_options['min'] = $schema['minLength'];
      }
      if (array_key_exists('maxLength', $schema)) {
        $length_options['max'] = $schema['maxLength'];
      }
      $constraints[] = new Length($length_options);
    }
    return $constraints;
  }

  /**
   * @param array $schema
   * @param array $constraints
   * @return array
   */
  protected function createRegexConstraint(array $schema, array $constraints): array {
    if (array_key_exists('format', $schema)) {
      if ($schema['format'] === 'uuid') {
        $constraints[] = CommandBase::getUuidRegexConstraint();
      }
    }
    elseif (array_key_exists('pattern', $schema)) {
      $constraints[] = new Regex([
        'message' => 'It must match the pattern ' . $schema['pattern'],
        'pattern' => '/' . $schema['pattern'] . '/',
      ]);
    }
    return $constraints;
  }

  /**
   * @param array $constraints
   */
  private function createValidatorFromConstraints(array $constraints): Closure {
    return static function ($value) use ($constraints) {
      $violations = Validation::createValidator()
        ->validate($value, $constraints);
      if (count($violations)) {
        throw new ValidatorException($violations->get(0)->getMessage());
      }
      return $value;
    };
  }

  protected function addQueryParamsToClient(InputInterface $input, Client $acquia_cloud_client): void {
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
  }

  private function addPostParamsToClient(InputInterface $input, Client $acquia_cloud_client): void {
    if ($this->postParams) {
      foreach ($this->postParams as $param_name => $param_spec) {
        $param_value = $this->getParamFromInput($input, $param_name);
        if (!is_null($param_value)) {
          $this->addPostParamToClient($param_name, $param_spec, $param_value, $acquia_cloud_client);
        }
      }
    }
  }

  /**
  * @param array|null $param_spec
  */
  private function addPostParamToClient(string $param_name, ?array $param_spec, mixed $param_value, Client $acquia_cloud_client): void {
    $param_name = ApiCommandHelper::restoreRenamedParameter($param_name);
    if ($param_spec) {
      $param_value = $this->castParamType($param_spec, $param_value);
    }
    if ($param_spec && array_key_exists('format', $param_spec) && $param_spec["format"] === 'binary') {
      $acquia_cloud_client->addOption('multipart', [
        [
          'contents' => Utils::tryFopen($param_value, 'r'),
          'name' => $param_name,
        ],
      ]);
    }
    else {
      $acquia_cloud_client->addOption('json', [$param_name => $param_value]);
    }
  }

  /**
   * @param array $params
   */
  private function askFreeFormQuestion(InputArgument $argument, array $params): mixed {
    // Default value may be an empty array, which causes Question to choke.
    $default = $argument->getDefault() ?: NULL;
    $question = new Question("Enter a value for {$argument->getName()}", $default);
    switch ($argument->getName()) {
      case 'applicationUuid':
        // @todo Provide a list of application UUIDs.
        $question->setValidator(function ($value) {
          return $this->validateApplicationUuid($value);
        });
        break;
      case 'environmentId':
        // @todo Provide a list of environment IDs.
      case 'source':
        $question->setValidator(function ($value) use ($argument) {
          return $this->validateEnvironmentUuid($value, $argument->getName());
        });
        break;

      default:
        $validator = $this->createCallableValidator($argument, $params);
        $question->setValidator($validator);
        break;
    }

    // Allow unlimited attempts.
    $question->setMaxAttempts(NULL);
    return $this->io->askQuestion($question);
  }

  /**
   * @param array $param_spec
   * @return null|array
   */
  private function getParamTypeOneOf(array $param_spec): ?array {
    $one_of = $param_spec['oneOf'] ?? NULL;
    if (array_key_exists('schema', $param_spec) && array_key_exists('oneOf', $param_spec['schema'])) {
      $one_of = $param_spec['schema']['oneOf'];
    }
    return $one_of;
  }

  private function castParamToArray(mixed $param_spec, array|string $original_value): string|array|bool|int {
    if (array_key_exists('items', $param_spec) && array_key_exists('type', $param_spec['items'])) {
      if (!is_array($original_value)) {
        $original_value = $this->doCastParamType('array', $original_value);
      }
      $item_type = $param_spec['items']['type'];
      $array = [];
      foreach ($original_value as $key => $v) {
        $array[$key] = $this->doCastParamType($item_type, $v);
      }
      return $array;
    }
    return $this->doCastParamType('array', $original_value);
  }

}
