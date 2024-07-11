<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\CommandFactoryInterface;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;

class ApiCommandHelper
{
    public function __construct(
        private ConsoleLogger $logger
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getApiCommands(string $acquiaCloudSpecFilePath, string $commandPrefix, CommandFactoryInterface $commandFactory): array
    {
        $acquiaCloudSpec = $this->getCloudApiSpec($acquiaCloudSpecFilePath);
        $apiCommands = $this->generateApiCommandsFromSpec($acquiaCloudSpec, $commandPrefix, $commandFactory);
        $apiListCommands = $this->generateApiListCommands($apiCommands, $commandPrefix, $commandFactory);
        return array_merge($apiCommands, $apiListCommands);
    }

    private function useCloudApiSpecCache(): bool
    {
        return !(getenv('ACQUIA_CLI_USE_CLOUD_API_SPEC_CACHE') === '0');
    }

    protected function addArgumentExampleToUsageForGetEndpoint(array $paramDefinition, string $usage): mixed
    {
        if (array_key_exists('example', $paramDefinition)) {
            if (is_array($paramDefinition['example'])) {
                $usage = reset($paramDefinition['example']);
            } elseif (is_string($paramDefinition['example']) && str_contains($paramDefinition['example'], ' ')) {
                $usage .= '"' . $paramDefinition['example'] . '" ';
            } else {
                $usage .= $paramDefinition['example'] . ' ';
            }
        }

        return $usage;
    }

    private function addOptionExampleToUsageForGetEndpoint(array $paramDefinition, string $usage): string
    {
        if (array_key_exists('example', $paramDefinition)) {
            $usage .= '--' . $paramDefinition['name'] . '="' . $paramDefinition['example'] . '" ';
        }

        return $usage;
    }

    private function addApiCommandParameters(array $schema, array $acquiaCloudSpec, ApiBaseCommand $command): void
    {
        $inputDefinition = [];
        $usage = '';

        // Parameters to be used in the request query and path.
        if (array_key_exists('parameters', $schema)) {
            [
                $queryInputDefinition,
                $queryParamUsageSuffix,
            ] = $this->addApiCommandParametersForPathAndQuery($schema, $acquiaCloudSpec);
            /** @var \Symfony\Component\Console\Input\InputOption|InputArgument $parameterDefinition */
            foreach ($queryInputDefinition as $parameterDefinition) {
                $parameterSpecification = $this->getParameterDefinitionFromSpec($parameterDefinition->getName(), $acquiaCloudSpec, $schema);
                if ($parameterSpecification['in'] === 'query') {
                    $command->addQueryParameter($parameterDefinition->getName(), $parameterSpecification);
                } elseif ($parameterSpecification['in'] === 'path') {
                    $command->addPathParameter($parameterDefinition->getName(), $parameterSpecification);
                }
            }
            $usage .= $queryParamUsageSuffix;
            $inputDefinition = array_merge($inputDefinition, $queryInputDefinition);
        }

        // Parameters to be used in the request body.
        if (array_key_exists('requestBody', $schema)) {
            [
                $bodyInputDefinition,
                $requestBodyParamUsageSuffix,
            ] = $this->addApiCommandParametersForRequestBody($schema, $acquiaCloudSpec);
            $requestBodySchema = $this->getRequestBodyFromParameterSchema($schema, $acquiaCloudSpec);
            /** @var \Symfony\Component\Console\Input\InputOption|InputArgument $parameterDefinition */
            foreach ($bodyInputDefinition as $parameterDefinition) {
                $parameterSpecification = $this->getPropertySpecFromRequestBodyParam($requestBodySchema, $parameterDefinition);
                $command->addPostParameter($parameterDefinition->getName(), $parameterSpecification);
            }
            $usage .= $requestBodyParamUsageSuffix;
            $inputDefinition = array_merge($inputDefinition, $bodyInputDefinition);
        }

        $command->setDefinition(new InputDefinition($inputDefinition));
        if ($usage) {
            $command->addUsage(rtrim($usage));
        }
        $this->addAliasUsageExamples($command, $inputDefinition, rtrim($usage));
    }

    /**
     * @return array<mixed>
     */
    private function addApiCommandParametersForRequestBody(array $schema, array $acquiaCloudSpec): array
    {
        $usage = '';
        $inputDefinition = [];
        $requestBodySchema = $this->getRequestBodyFromParameterSchema($schema, $acquiaCloudSpec);

        if (!array_key_exists('properties', $requestBodySchema)) {
            return [];
        }
        foreach ($requestBodySchema['properties'] as $propKey => $paramDefinition) {
            $isRequired = array_key_exists('required', $requestBodySchema) && in_array($propKey, $requestBodySchema['required'], true);
            $propKey = self::renameParameter($propKey);

            if ($isRequired) {
                if (!array_key_exists('description', $paramDefinition)) {
                    $description = $paramDefinition["additionalProperties"]["description"];
                } else {
                    $description = $paramDefinition['description'];
                }
                $inputDefinition[] = new InputArgument(
                    $propKey,
                    array_key_exists('type', $paramDefinition) && $paramDefinition['type'] === 'array' ? InputArgument::IS_ARRAY | InputArgument::REQUIRED : InputArgument::REQUIRED,
                    $description
                );
                $usage = $this->addPostArgumentUsageToExample($schema['requestBody'], $propKey, $paramDefinition, 'argument', $usage);
            } else {
                $inputDefinition[] = new InputOption(
                    $propKey,
                    null,
                    array_key_exists('type', $paramDefinition) && $paramDefinition['type'] === 'array' ? InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED : InputOption::VALUE_REQUIRED,
                    array_key_exists('description', $paramDefinition) ? $paramDefinition['description'] : $propKey
                );
                $usage = $this->addPostArgumentUsageToExample($schema["requestBody"], $propKey, $paramDefinition, 'option', $usage);
                // @todo Add validator for $param['enum'] values?
            }
        }
        /** @var \Symfony\Component\Console\Input\InputArgument|InputOption $parameterDefinition */
        foreach ($inputDefinition as $index => $parameterDefinition) {
            if ($parameterDefinition->isArray()) {
                // Move to the end of the array.
                unset($inputDefinition[$index]);
                $inputDefinition[] = $parameterDefinition;
            }
        }

        return [$inputDefinition, $usage];
    }

    private function addPostArgumentUsageToExample(mixed $requestBody, mixed $propKey, mixed $paramDefinition, string $type, string $usage): string
    {
        $requestBodyContent = $this->getRequestBodyContent($requestBody);

        if (array_key_exists('example', $requestBodyContent)) {
            $example = $requestBodyContent['example'];
            $prefix = $type === 'argument' ? '' : "--{$propKey}=";
            if (array_key_exists($propKey, $example)) {
                switch ($paramDefinition['type']) {
                    case 'object':
                        $usage .= $prefix . '"' . json_encode($example[$propKey], JSON_THROW_ON_ERROR) . '"" ';
                        break;

                    case 'array':
                        $isMultidimensional = count($example[$propKey]) !== count($example[$propKey], COUNT_RECURSIVE);
                        if (!$isMultidimensional) {
                            foreach ($example[$propKey] as $value) {
                                $usage .= $prefix . "\"$value\" ";
                            }
                        } else {
                            // @todo Pretty sure prevents the user from using the arguments.
                            // Probably a bug. How can we allow users to specify a multidimensional array as an
                            // argument?
                            $value = json_encode($example[$propKey], JSON_THROW_ON_ERROR);
                            $usage .= $prefix . "\"$value\" ";
                        }
                        break;

                    case 'string':
                    case 'boolean':
                    case 'integer':
                        if (is_array($example[$propKey])) {
                            $value = reset($example[$propKey]);
                        } else {
                            $value = $example[$propKey];
                        }
                        $usage .= $prefix . "\"{$value}\" ";
                        break;
                }
            }
        }
        return $usage;
    }

    /**
     * @return array<mixed>
     */
    private function addApiCommandParametersForPathAndQuery(array $schema, array $acquiaCloudSpec): array
    {
        $usage = '';
        $inputDefinition = [];
        if (!array_key_exists('parameters', $schema)) {
            return [];
        }
        foreach ($schema['parameters'] as $parameter) {
            if (array_key_exists('$ref', $parameter)) {
                $parts = explode('/', $parameter['$ref']);
                $paramKey = end($parts);
                $paramDefinition = $this->getParameterDefinitionFromSpec($paramKey, $acquiaCloudSpec, $schema);
            } else {
                $paramDefinition = $parameter;
            }

            $required = array_key_exists('required', $paramDefinition) && $paramDefinition['required'];
            $this->addAliasParameterDescriptions($paramDefinition);
            if ($required) {
                $inputDefinition[] = new InputArgument(
                    $paramDefinition['name'],
                    InputArgument::REQUIRED,
                    $paramDefinition['description']
                );
                $usage = $this->addArgumentExampleToUsageForGetEndpoint($paramDefinition, $usage);
            } else {
                $inputDefinition[] = new InputOption(
                    $paramDefinition['name'],
                    null,
                    InputOption::VALUE_REQUIRED,
                    $paramDefinition['description']
                );
                $usage = $this->addOptionExampleToUsageForGetEndpoint($paramDefinition, $usage);
            }
        }

        return [$inputDefinition, $usage];
    }

    private function getParameterDefinitionFromSpec(string $paramKey, array $acquiaCloudSpec, mixed $schema): mixed
    {
        $uppercaseKey = ucfirst($paramKey);
        if (
            array_key_exists('parameters', $acquiaCloudSpec['components'])
            && array_key_exists($uppercaseKey, $acquiaCloudSpec['components']['parameters'])
        ) {
            return $acquiaCloudSpec['components']['parameters'][$uppercaseKey];
        }
        foreach ($schema['parameters'] as $parameter) {
            if ($parameter['name'] === $paramKey) {
                return $parameter;
            }
        }
        return null;
    }

    private function getParameterSchemaFromSpec(string $paramKey, array $acquiaCloudSpec): mixed
    {
        return $acquiaCloudSpec['components']['schemas'][$paramKey];
    }

    // @infection-ignore-all
    private function isApiSpecChecksumCacheValid(\Symfony\Component\Cache\CacheItem $cacheItem, string $acquiaCloudSpecFileChecksum): bool
    {
        // If the spec file doesn't exist, assume cache is valid.
        if (!$acquiaCloudSpecFileChecksum && $cacheItem->isHit()) {
            return true;
        }
        // If there's an invalid entry OR there's no entry, return false.
        if (!$cacheItem->isHit() || $cacheItem->get() !== $acquiaCloudSpecFileChecksum) {
            return false;
        }
        return true;
    }

    /**
     * Breaking caching during tests will cause performance issues and timeouts.
     *
     * @return array<mixed>
     * @infection-ignore-all
     */
    private function getCloudApiSpec(string $specFilePath): array
    {
        $cacheKey = basename($specFilePath);
        $cache = new PhpArrayAdapter(__DIR__ . '/../../../var/cache/' . $cacheKey . '.cache', new NullAdapter());
        $cacheItemChecksum = $cache->getItem($cacheKey . '.checksum');
        $cacheItemSpec = $cache->getItem($cacheKey);

        // When running the phar, the original file may not exist. In that case, always use the cache.
        if (!file_exists($specFilePath) && $cacheItemSpec->isHit()) {
            return $cacheItemSpec->get();
        }

        // Otherwise, only use cache when it is valid.
        $checksum = md5_file($specFilePath);
        // @infection-ignore-all
        if (
            $this->useCloudApiSpecCache()
            && $this->isApiSpecChecksumCacheValid($cacheItemChecksum, $checksum) && $cacheItemSpec->isHit()
        ) {
            return $cacheItemSpec->get();
        }

        // Parse file. This can take a long while!
        $this->logger->debug("Rebuilding caches...");
        $spec = json_decode(file_get_contents($specFilePath), true);

        $cache->warmUp([
            $cacheKey => $spec,
            $cacheKey . '.checksum' => $checksum,
        ]);

        return $spec;
    }

    /**
     * @return ApiBaseCommand[]
     */
    private function generateApiCommandsFromSpec(array $acquiaCloudSpec, string $commandPrefix, CommandFactoryInterface $commandFactory): array
    {
        $apiCommands = [];
        foreach ($acquiaCloudSpec['paths'] as $path => $endpoint) {
            foreach ($endpoint as $method => $schema) {
                if (!array_key_exists('x-cli-name', $schema)) {
                    continue;
                }

                if (in_array($schema['x-cli-name'], $this->getSkippedApiCommands(), true)) {
                    continue;
                }

                // Skip deprecated endpoints.
                // @infection-ignore-all
                if (array_key_exists('deprecated', $schema) && $schema['deprecated']) {
                    continue;
                }

                $commandName = $commandPrefix . ':' . $schema['x-cli-name'];
                $command = $commandFactory->createCommand();
                $command->setName($commandName);
                $command->setDescription($schema['summary']);
                $command->setMethod($method);
                $command->setResponses($schema['responses']);
                $command->setHidden(false);
                if (array_key_exists('servers', $acquiaCloudSpec)) {
                    $command->setServers($acquiaCloudSpec['servers']);
                }
                $command->setPath($path);
                $command->setHelp("For more help, see https://cloudapi-docs.acquia.com/ or https://dev.acquia.com/api-documentation/acquia-cloud-site-factory-api for acsf commands.");
                $this->addApiCommandParameters($schema, $acquiaCloudSpec, $command);
                $apiCommands[] = $command;
            }
        }

        return $apiCommands;
    }

    /**
     * @return array<mixed>
     */
    protected function getSkippedApiCommands(): array
    {
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

    private function addAliasUsageExamples(ApiBaseCommand $command, array $inputDefinition, string $usage): void
    {
        foreach ($inputDefinition as $key => $parameter) {
            if ($parameter->getName() === 'applicationUuid') {
                $usageParts = explode(' ', $usage);
                $usageParts[$key] = "myapp";
                $usage = implode(' ', $usageParts);
                $command->addUsage($usage);
            }
            if ($parameter->getName() === 'environmentId') {
                $usageParts = explode(' ', $usage);
                $usageParts[$key] = "myapp.dev";
                $usage = implode(' ', $usageParts);
                $command->addUsage($usage);
            }
        }
    }

    private function addAliasParameterDescriptions(mixed &$paramDefinition): void
    {
        if ($paramDefinition['name'] === 'applicationUuid') {
            $paramDefinition['description'] .= ' You may also use an application alias or omit the argument if you run the command in a linked directory.';
        }
        if ($paramDefinition['name'] === 'environmentId') {
            $paramDefinition['description'] .= " You may also use an environment alias or UUID.";
        }
    }

    /**
     * @return array<mixed>
     */
    private function getRequestBodyFromParameterSchema(array $schema, array $acquiaCloudSpec): array
    {
        $requestBodyContent = $this->getRequestBodyContent($schema['requestBody']);
        $requestBodySchema = $requestBodyContent['schema'];

        // If this is a reference to the top level schema, go grab the referenced component.
        if (array_key_exists('$ref', $requestBodySchema)) {
            $parts = explode('/', $requestBodySchema['$ref']);
            $paramKey = end($parts);
            $requestBodySchema = $this->getParameterSchemaFromSpec($paramKey, $acquiaCloudSpec);
        }

        return $requestBodySchema;
    }

    private function getPropertySpecFromRequestBodyParam(array $requestBodySchema, mixed $parameterDefinition): mixed
    {
        return $requestBodySchema['properties'][$parameterDefinition->getName()] ?? null;
    }

    /**
     * @return array<mixed>
     */
    protected static function getParameterRenameMap(): array
    {
        // Format should be ['original => new'].
        return [
            // @see api:environments:cron-create
            'command' => 'cron_command',
            // @see api:environments:update.
            'version' => 'lang_version',
        ];
    }

    public static function renameParameter(mixed $propKey): mixed
    {
        $parameterRenameMap = self::getParameterRenameMap();
        if (array_key_exists($propKey, $parameterRenameMap)) {
            $propKey = $parameterRenameMap[$propKey];
        }
        return $propKey;
    }

    public static function restoreRenamedParameter(string $propKey): int|string
    {
        $parameterRenameMap = array_flip(self::getParameterRenameMap());
        if (array_key_exists($propKey, $parameterRenameMap)) {
            $propKey = $parameterRenameMap[$propKey];
        }
        return $propKey;
    }

    /**
     * @return ApiListCommandBase[]
     */
    private function generateApiListCommands(array $apiCommands, string $commandPrefix, CommandFactoryInterface $commandFactory): array
    {
        $apiListCommands = [];
        foreach ($apiCommands as $apiCommand) {
            $commandNameParts = explode(':', $apiCommand->getName());
            if (count($commandNameParts) < 3) {
                continue;
            }
            $namespace = $commandNameParts[1];
            if (!array_key_exists($namespace, $apiListCommands)) {
                /** @var \Acquia\Cli\Command\Acsf\AcsfListCommand|\Acquia\Cli\Command\Api\ApiListCommand $command */
                $command = $commandFactory->createListCommand();
                $name = $commandPrefix . ':' . $namespace;
                $command->setName($name);
                $command->setNamespace($name);
                $command->setAliases([]);
                $command->setDescription("List all API commands for the {$namespace} resource");
                $apiListCommands[$name] = $command;
            }
        }
        return $apiListCommands;
    }

    /**
     * @param array<mixed> $requestBody
     * @return array<mixed>
     */
    private function getRequestBodyContent(array $requestBody): array
    {
        $content = $requestBody['content'];
        $knownContentTypes = [
            'application/hal+json',
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
        ];
        foreach ($knownContentTypes as $contentType) {
            if (array_key_exists($contentType, $content)) {
                return $content[$contentType];
            }
        }
        throw new AcquiaCliException("requestBody content doesn't match any known schema");
    }
}
