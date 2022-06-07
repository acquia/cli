<?php

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
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

/**
 * Class ApiBaseCommand.
 */
class ApiBaseCommand extends CommandBase {

  protected static $defaultName = 'api:base';

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
   *
   */
  protected function configure() {
    $this->setHidden(TRUE);
    parent::configure();
  }

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
   */
  public function interact(InputInterface $input, OutputInterface $output) {
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
          $answer = $this->io->choice("Please select a value for {$argument->getName()}", $choices, $argument->getDefault());
        }
        elseif (array_key_exists($argument->getName(), $params)
          && array_key_exists('type', $params[$argument->getName()])
          && $params[$argument->getName()]['type'] === 'boolean') {
          $answer = $this->io->choice("Please select a value for {$argument->getName()}", ['true', 'false'], $argument->getDefault());
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
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

    $contents = json_encode($response, JSON_PRETTY_PRINT);
    $this->output->writeln($contents);

    return $exit_code;
  }

  /**
   * @param string $method
   */
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

  /**
   * @param string $path
   */
  public function setPath(string $path): void {
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
      if (str_contains($path, $token)) {
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

  /**
   * @return string
   */
  public function getPath(): string {
    return $this->path;
  }

  /**
   * @param string $param_name
   * @param $value
   */
  public function addPathParameter($param_name, $value): void {
    $this->pathParams[$param_name] = $value;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param string $param_name
   *
   * @return bool|string|string[]|null
   */
  protected function getParamFromInput(InputInterface $input, string $param_name) {
    if ($input->hasArgument($param_name)) {
      $param = $input->getArgument($param_name);
    }
    else {
      $param = $input->getOption($param_name);
    }
    return $param;
  }

  /**
   * @param array $param_spec
   * @param string|array $value
   *
   * @return bool|int|string
   */
  protected function castParamType(array $param_spec, $value) {
    if (array_key_exists('schema', $param_spec) && array_key_exists('oneOf', $param_spec['schema'])) {
      $types = [];
      foreach ($param_spec['schema']['oneOf'] as $type) {
        $types[] = $type['type'];
      }
      if (array_search('array', $types) && str_contains($value, ',')) {
        return $this->doCastParamType('array', $value);
      }
      if ((array_search('integer', $types) !== FALSE || array_search('int', $types) !== FALSE)
        && ctype_digit($value)) {
        return $this->doCastParamType('integer', $value);
      }
    }

    $type = $this->getParamType($param_spec);
    if (!$type) {
      return $value;
    }

    return $this->doCastParamType($type, $value);
  }

  /**
   * @param $type
   * @param $value
   *
   * @return bool|int|string
   */
  protected function doCastParamType($type, $value) {
    return match ($type) {
      'int', 'integer' => (int) $value,
      'bool', 'boolean' => $this->castBool($value),
      'array' => is_string($value) ? explode(',', $value): $value,
      'string' => (string) $value,
      'mixed' => $value,
    };
  }

  /**
   * @param $val
   *
   * @return bool
   */
  function castBool($val): bool {
    return (bool) (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : $val);
  }

  /**
   * @param array $param_spec
   *
   * @return null|string
   */
  protected function getParamType(array $param_spec): ?string {
    // @todo File a CXAPI ticket regarding the inconsistent nesting of the 'type' property.
    if (array_key_exists('type', $param_spec)) {
      return $param_spec['type'];
    }
    elseif (array_key_exists('schema', $param_spec) && array_key_exists('type', $param_spec['schema'])) {
      return $param_spec['schema']['type'];
    }
    return NULL;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputArgument $argument
   * @param array $params
   *
   * @return callable|null
   */
  protected function createCallableValidator(InputArgument $argument, array $params): ?callable {
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
   *
   * @return array
   */
  protected function createLengthConstraint($schema, array $constraints): array {
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
   *
   * @return array
   */
  protected function createRegexConstraint($schema, array $constraints): array {
    if (array_key_exists('format', $schema)) {
      switch ($schema['format']) {
        case 'uuid';
          $constraints[] = CommandBase::getUuidRegexConstraint();
          break;
      }
    }
    elseif (array_key_exists('pattern', $schema)) {
      $constraints[] = new Regex([
        'pattern' => '/' . $schema['pattern'] . '/',
        'message' => 'It must match the pattern ' . $schema['pattern'],
      ]);
    }
    return $constraints;
  }

  /**
   * @param array $constraints
   *
   * @return \Closure
   */
  protected function createValidatorFromConstraints(array $constraints): \Closure {
    return function ($value) use ($constraints) {
      $violations = Validation::createValidator()
        ->validate($value, $constraints);
      if (count($violations)) {
        throw new ValidatorException($violations->get(0)->getMessage());
      }
      return $value;
    };
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   */
  protected function addQueryParamsToClient(InputInterface $input, Client $acquia_cloud_client) {
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   */
  protected function addPostParamsToClient(InputInterface $input, Client $acquia_cloud_client): void {
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
  * @param string $param_name
  * @param array|null $param_spec
  * @param mixed $param_value
  * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
  */
  protected function addPostParamToClient(string $param_name, $param_spec, $param_value, Client $acquia_cloud_client) {
    $param_name = ApiCommandHelper::restoreRenamedParameter($param_name);
    if ($param_spec) {
      $param_value = $this->castParamType($param_spec, $param_value);
    }
    if ($param_spec && array_key_exists('format', $param_spec) && $param_spec["format"] === 'binary') {
      $acquia_cloud_client->addOption('multipart', [
        [
          'name' => $param_name,
          'contents' => Utils::tryFopen($param_value, 'r'),
        ],
      ]);
    }
    else {
      $acquia_cloud_client->addOption('json', [$param_name => $param_value]);
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputArgument $argument
   * @param array $params
   *
   * @return mixed
   */
  protected function askFreeFormQuestion(InputArgument $argument, array $params) {
    $question = new Question("Please enter a value for {$argument->getName()}", $argument->getDefault());
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

}
