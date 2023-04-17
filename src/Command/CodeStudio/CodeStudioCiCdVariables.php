<?php

namespace Acquia\Cli\Command\CodeStudio;

class CodeStudioCiCdVariables {

  /**
   * @return array
   */
  public static function getList(): array {
    return array_column(self::getDefaults(), 'key');
  }

  /**
   * @return array[]
   */
  public static function getDefaults(?string $cloud_application_uuid = NULL, ?string $cloud_key = NULL, ?string $cloud_secret = NULL, ?string $project_access_token_name = NULL, ?string $project_access_token = NULL): array {
    return [
      [
        'key' => 'ACQUIA_APPLICATION_UUID',
        'masked' => FALSE,
        'protected' => FALSE,
        'value' => $cloud_application_uuid,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
        'masked' => FALSE,
        'protected' => FALSE,
        'value' => $cloud_key,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
        'masked' => FALSE,
        'protected' => FALSE,
        'value' => $cloud_secret,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_NAME',
        'masked' => FALSE,
        'protected' => FALSE,
        'value' => $project_access_token_name,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
        'masked' => TRUE,
        'protected' => FALSE,
        'value' => $project_access_token,
        'variable_type' => 'env_var',
      ],
    ];
  }

}
