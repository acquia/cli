<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use AcquiaCloudApi\Endpoints\CloudApiBase;

/**
 * SAS API endpoints for Source site configuration.
 *
 * Both directions are thin triggers: SAS runs a `drush source:config:*`
 * command on the environment, and the config moves between the CMS and the
 * site's git repository on the Acquia/GitHub side. No config payload travels
 * through these requests.
 *
 * @todo DXBE-20: Confirm the endpoint paths and response field names with the
 *   SAS team. The SAS endpoints do not exist yet; paths here are placeholders.
 */
class SourceConfig extends CloudApiBase
{
    /**
     * Trigger a config import on a site environment (repo to CMS).
     *
     * @return object The decoded response, expected to contain an operation ID.
     */
    public function push(string $environmentId): object
    {
        return $this->client->request('post', "/environments/$environmentId/config-import");
    }

    /**
     * Trigger a config export on a site environment (CMS to repo).
     *
     * @return object The decoded response, expected to contain an operation ID.
     */
    public function pull(string $environmentId): object
    {
        return $this->client->request('post', "/environments/$environmentId/config-export");
    }

    /**
     * Get the status of a config operation.
     *
     * @return object The decoded response, expected to contain a status field.
     */
    public function getStatus(string $operationId): object
    {
        return $this->client->request('get', "/config-operation/$operationId");
    }
}
