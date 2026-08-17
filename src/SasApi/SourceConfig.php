<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use AcquiaCloudApi\Endpoints\CloudApiBase;

/**
 * SAS API endpoints for Source site configuration.
 *
 * @todo DXBE-20: Confirm the endpoint paths and response field names with the
 *   SAS team. The SAS endpoints do not exist yet; paths here are placeholders.
 */
class SourceConfig extends CloudApiBase
{
    /**
     * Trigger a config import on a site environment.
     *
     * Sends no payload — the config is read from the site's deployed git
     * repository on the Acquia side (via `drush source:config:import`).
     *
     * @return object The decoded response, expected to contain an operation ID.
     */
    public function push(string $environmentId): object
    {
        return $this->client->request('post', "/environments/$environmentId/config-import");
    }

    /**
     * Get the status of a config import operation.
     *
     * @return object The decoded response, expected to contain a status field.
     */
    public function getPushStatus(string $operationId): object
    {
        return $this->client->request('get', "/config-import/$operationId");
    }
}
