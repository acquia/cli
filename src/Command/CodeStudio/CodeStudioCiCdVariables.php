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
   * @param string|null $cloud_application_uuid
   * @param string|null $cloud_key
   * @param string|null $cloud_secret
   * @param string|null $project_access_token_name
   * @param string|null $project_access_token
   *
   * @return array[]
   */
  public static function getDefaults(?string $cloud_application_uuid = NULL, ?string $cloud_key = NULL, ?string $cloud_secret = NULL, ?string $project_access_token_name = NULL, ?string $project_access_token = NULL): array {
    return [
      [
        'key' => 'ACQUIA_APPLICATION_UUID',
        'value' => $cloud_application_uuid,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_KEY',
        'value' => $cloud_key,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_CLOUD_API_TOKEN_SECRET',
        'value' => $cloud_secret,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_NAME',
        'value' => $project_access_token_name,
        'masked' => FALSE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
      [
        'key' => 'ACQUIA_GLAB_TOKEN_SECRET',
        'value' => $project_access_token,
        'masked' => TRUE,
        'protected' => FALSE,
        'variable_type' => 'env_var',
      ],
    ];
  }

}
