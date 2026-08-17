<?php

declare(strict_types=1);

namespace Acquia\Cli\SasApi;

use AcquiaCloudApi\Endpoints\CloudApiBase;

/**
 * SAS API endpoints for pushing Source site configuration.
 *
 * @todo DXBE-20: Confirm the endpoint paths and response field names with the
 *   SAS team. The SAS endpoints do not exist yet; paths here are placeholders.
 */
class SourceConfig extends CloudApiBase
{
    /**
     * Submit a config push for a site environment.
     *
     * The payload is sent as a single YAML document mapping config collection
     * names to config items, mirroring the structure produced by
     * `drush source:config:dump --single-yaml`.
     *
     * @todo DXBE-20: The SAS team may require the payload JSON-encoded
     *   instead. If so, replace the YAML body and Content-Type with
     *   json_encode() and the json option.
     * @return object The decoded response, expected to contain an operation ID.
     */
    public function push(string $environmentId, string $yamlPayload): object
    {
        $options = [
            'body' => $yamlPayload,
            'headers' => ['Content-Type' => 'application/yaml'],
        ];

        return $this->client->request(
            'post',
            "/environments/$environmentId/config-push",
            $options,
        );
    }

    /**
     * Get the status of a config push operation.
     *
     * @return object The decoded response, expected to contain a status field.
     */
    public function getPushStatus(string $operationId): object
    {
        return $this->client->request('get', "/config-push/$operationId");
    }
}
