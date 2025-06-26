<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\CodeStudio;

class CodeStudioCiCdVariables
{
    /**
     * @return array<mixed>
     */
    public static function getList(): array
    {
        // Getlist is being utilised in pipeline-migrate command. By default command is supporting drupal project but going forward need to support both drupal and nodejs project.
        return array_column(self::getDefaultsForPhp(EntityType::Application), 'key');
    }

    /**
     * @return array<mixed>
     */
    public static function getDefaultsForNode(?string $cloudApplicationUuid = null, ?string $cloudKey = null, ?string $cloudSecret = null, ?string $projectAccessTokenName = null, ?string $projectAccessToken = null, ?string $nodeVersion = null, ?string $nodeHostingType = null): array
    {
        return [
            [
                'key' => 'ACQUIA_APPLICATION_UUID',
                'masked' => true,
                'protected' => false,
                'value' => $cloudApplicationUuid,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
                'masked' => true,
                'protected' => false,
                'value' => $cloudKey,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
                'masked' => true,
                'protected' => false,
                'value' => $cloudSecret,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_GLAB_TOKEN_NAME',
                'masked' => true,
                'protected' => false,
                'value' => $projectAccessTokenName,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
                'masked' => true,
                'protected' => false,
                'value' => $projectAccessToken,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'NODE_VERSION',
                'masked' => false,
                'protected' => false,
                'value' => $nodeVersion,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'NODE_HOSTING_TYPE',
                'masked' => false,
                'protected' => false,
                'value' => $nodeHostingType,
                'variable_type' => 'env_var',
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function getDefaultsForPhp(EntityType $entityType, ?string $cloudUuid = null, ?string $cloudKey = null, ?string $cloudSecret = null, ?string $projectAccessTokenName = null, ?string $projectAccessToken = null, ?string $mysqlVersion = null, ?string $phpVersion = null): array
    {
        $vars = [];

        if ($entityType === EntityType::Codebase) {
            $vars[] = [
                'key' => 'ACQUIA_CODEBASE_UUID',
                'masked' => true,
                'protected' => false,
                'value' => $cloudUuid,
                'variable_type' => 'env_var',
            ];
        } else {
            $vars[] = [
                'key' => 'ACQUIA_APPLICATION_UUID',
                'masked' => true,
                'protected' => false,
                'value' => $cloudUuid,
                'variable_type' => 'env_var',
            ];
        }

        $vars = array_merge($vars, [
            [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
                'masked' => true,
                'protected' => false,
                'value' => $cloudKey,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
                'masked' => true,
                'protected' => false,
                'value' => $cloudSecret,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_GLAB_TOKEN_NAME',
                'masked' => true,
                'protected' => false,
                'value' => $projectAccessTokenName,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
                'masked' => true,
                'protected' => false,
                'value' => $projectAccessToken,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'MYSQL_VERSION',
                'masked' => false,
                'protected' => false,
                'value' => $mysqlVersion,
                'variable_type' => 'env_var',
            ],
            [
                'key' => 'PHP_VERSION',
                'masked' => false,
                'protected' => false,
                'value' => $phpVersion,
                'variable_type' => 'env_var',
            ],
        ]);

        return $vars;
    }
}
