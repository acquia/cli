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

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'api:base';

  protected string $method;

  /**
   * @var array<mixed>
   */
  protected array $responses;

  /**
   * @var array<mixed>
   */
  protected array $servers;

  protected string $path;

  /**
   * @var array<mixed>
   */
  private array $queryParams = [];

  /**
   * @var array<mixed>
   */
  private array $postParams = [];

  /**
   * @var array<mixed>
   */
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

  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Build query from non-null options.
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $this->addQueryParamsToClient($input, $acquiaCloudClient);
    $this->addPostParamsToClient($input, $acquiaCloudClient);
    $acquiaCloudClient->addOption('headers', [
      'Accept' => 'application/json',
    ]);

    try {
      if ($this->output->isVeryVerbose()) {
        $acquiaCloudClient->addOption('debug', $this->output);
      }
      $path = $this->getRequestPath($input);
      $response = $acquiaCloudClient->request($this->method, $path);
      $exitCode = 0;
    }
    catch (ApiErrorException $exception) {
      $response = $exception->getResponseBody();
      $exitCode = 1;
    }

    $contents = json_encode($response, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $this->output->writeln($contents);

    return $exitCode;
  }

  public function setMethod(string $method): void {
    $this->method = $method;
  }

  public function setResponses(array $responses): void {
    $this->responses = $responses;
  }

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

  public function addPostParameter(mixed $paramName, mixed $value): void {
    $this->postParams[$paramName] = $value;
  }

  public function addQueryParameter(mixed $paramName, mixed $value): void {
    $this->queryParams[$paramName] = $value;
  }

  public function getPath(): string {
    return $this->path;
  }

  public function addPathParameter(string $paramName, mixed $value): void {
    $this->pathParams[$paramName] = $value;
  }

  /**
   * @return bool|string|string[]|null
   */
  private function getParamFromInput(InputInterface $input, string $paramName): array|bool|string|null {
    if ($input->hasArgument($paramName)) {
      return $input->getArgument($paramName);
    }

    if ($input->hasParameterOption('--' . $paramName)) {
      return $input->getOption($paramName);
    }
    return NULL;
  }

  private function castParamType(array $paramSpec, array|string $value): array|bool|int|string {
    $oneOf = $this->getParamTypeOneOf($paramSpec);
    if (isset($oneOf)) {
      $types = [];
      foreach ($oneOf as $type) {
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
    elseif ($paramSpec['type'] === 'array') {
      if (is_array($value) && count($value) === 1) {
        return $this->castParamToArray($paramSpec, $value[0]);
      }

      return $this->castParamToArray($paramSpec, $value);
    }

    $type = $this->getParamType($paramSpec);
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

  public function castBool(mixed $val): bool {
    return (bool) (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : $val);
  }

  private function getParamType(array $paramSpec): ?string {
    // @todo File a CXAPI ticket regarding the inconsistent nesting of the 'type' property.
    if (array_key_exists('type', $paramSpec)) {
      return $paramSpec['type'];
    }

    if (array_key_exists('schema', $paramSpec) && array_key_exists('type', $paramSpec['schema'])) {
      return $paramSpec['schema']['type'];
    }
    return NULL;
  }

  private function createCallableValidator(InputArgument $argument, array $params): ?callable {
    $validator = NULL;
    if (array_key_exists($argument->getName(), $params)) {
      $paramSpec = $params[$argument->getName()];
      $constraints = [
        new NotBlank(),
      ];
      if ($type = $this->getParamType($paramSpec)) {
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
      if (array_key_exists('schema', $paramSpec)) {
        $schema = $paramSpec['schema'];
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
   * @return array<mixed>
   */
  private function createLengthConstraint(array $schema, array $constraints): array {
    if (array_key_exists('minLength', $schema) || array_key_exists('maxLength', $schema)) {
      $lengthOptions = [];
      if (array_key_exists('minLength', $schema)) {
        $lengthOptions['min'] = $schema['minLength'];
      }
      if (array_key_exists('maxLength', $schema)) {
        $lengthOptions['max'] = $schema['maxLength'];
      }
      $constraints[] = new Length($lengthOptions);
    }
    return $constraints;
  }

  /**
   * @param array $schema
   * @param array $constraints
   * @return array<mixed>
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

  private function createValidatorFromConstraints(array $constraints): Closure {
    return static function (mixed $value) use ($constraints) {
      $violations = Validation::createValidator()
        ->validate($value, $constraints);
      if (count($violations)) {
        throw new ValidatorException($violations->get(0)->getMessage());
      }
      return $value;
    };
  }

  protected function addQueryParamsToClient(InputInterface $input, Client $acquiaCloudClient): void {
    if ($this->queryParams) {
      foreach ($this->queryParams as $key => $paramSpec) {
        // We may have a queryParam that is used in the path rather than the query string.
        if ($input->hasOption($key) && $input->getOption($key) !== NULL) {
          $acquiaCloudClient->addQuery($key, $input->getOption($key));
        }
        elseif ($input->hasArgument($key) && $input->getArgument($key) !== NULL) {
          $acquiaCloudClient->addQuery($key, $input->getArgument($key));
        }
      }
    }
  }

  private function addPostParamsToClient(InputInterface $input, Client $acquiaCloudClient): void {
    if ($this->postParams) {
      foreach ($this->postParams as $paramName => $paramSpec) {
        $paramValue = $this->getParamFromInput($input, $paramName);
        if (!is_null($paramValue)) {
          $this->addPostParamToClient($paramName, $paramSpec, $paramValue, $acquiaCloudClient);
        }
      }
    }
  }

  /**
  * @param array|null $paramSpec
  */
  private function addPostParamToClient(string $paramName, ?array $paramSpec, mixed $paramValue, Client $acquiaCloudClient): void {
    $paramName = ApiCommandHelper::restoreRenamedParameter($paramName);
    if ($paramSpec) {
      $paramValue = $this->castParamType($paramSpec, $paramValue);
    }
    if ($paramSpec && array_key_exists('format', $paramSpec) && $paramSpec["format"] === 'binary') {
      $acquiaCloudClient->addOption('multipart', [
        [
          'contents' => Utils::tryFopen($paramValue, 'r'),
          'name' => $paramName,
        ],
      ]);
    }
    else {
      $acquiaCloudClient->addOption('json', [$paramName => $paramValue]);
    }
  }

  private function askFreeFormQuestion(InputArgument $argument, array $params): mixed {
    // Default value may be an empty array, which causes Question to choke.
    $default = $argument->getDefault() ?: NULL;
    $question = new Question("Enter a value for {$argument->getName()}", $default);
    switch ($argument->getName()) {
      case 'applicationUuid':
        // @todo Provide a list of application UUIDs.
        $question->setValidator(function (mixed $value) {
          return $this->validateApplicationUuid($value);
        });
        break;
      case 'environmentId':
        // @todo Provide a list of environment IDs.
      case 'source':
        $question->setValidator(function (mixed $value) use ($argument): string {
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
   * @param array $paramSpec
   * @return null|array<mixed>
   */
  private function getParamTypeOneOf(array $paramSpec): ?array {
    $oneOf = $paramSpec['oneOf'] ?? NULL;
    if (array_key_exists('schema', $paramSpec) && array_key_exists('oneOf', $paramSpec['schema'])) {
      $oneOf = $paramSpec['schema']['oneOf'];
    }
    return $oneOf;
  }

  private function castParamToArray(mixed $paramSpec, array|string $originalValue): string|array|bool|int {
    if (array_key_exists('items', $paramSpec) && array_key_exists('type', $paramSpec['items'])) {
      if (!is_array($originalValue)) {
        $originalValue = $this->doCastParamType('array', $originalValue);
      }
      $itemType = $paramSpec['items']['type'];
      $array = [];
      foreach ($originalValue as $key => $v) {
        $array[$key] = $this->doCastParamType($itemType, $v);
      }
      return $array;
    }
    return $this->doCastParamType('array', $originalValue);
  }

}
