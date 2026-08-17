<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use AcquiaCloudApi\Endpoints\CloudApiBase;

/**
 * SAS API endpoints for Source site configuration.
 *
 * Both directions are thin triggers over the SAS API: SAS runs a
 * `drush source:config:*` command on the environment, and the config moves
 * between the CMS and the site's git repository. Push (import) sends no
 * payload; pull (export) returns the exported config as a YAML document once
 * the operation completes.
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

    /**
     * Get the exported config payload for a completed export operation.
     *
     * @todo DXBE-20: Confirm how the YAML payload is returned (response body
     *   vs. a field on the status resource) and its content type. Assumes a
     *   raw YAML body here.
     * @return string The exported config as a YAML document.
     */
    public function getExportPayload(string $operationId): string
    {
        $response = $this->client->request('get', "/config-operation/$operationId/payload");

        // The client may return the body as a string (YAML) or as a decoded
        // object carrying the YAML in a field. Handle both.
        if (is_string($response)) {
            return $response;
        }

        // @todo DXBE-20: Confirm the field name with the SAS team.
        return $response->payload ?? '';
    }
}
