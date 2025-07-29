<?php

declare(strict_types=1);

namespace Acquia\Cli\Transformer;

use AcquiaCloudApi\Response\EnvironmentResponse;

/**
 * Transformer for converting site instance environments to environment response objects.
 *
 * This transformer converts various environment object types (like CodebaseEnvironmentResponse
 * from v3 API) to the standard EnvironmentResponse format expected by the codebase.
 */
class EnvironmentTransformer
{
    /**
     * Transform any environment-like object to EnvironmentResponse.
     *
     * This transformer converts various environment object types (like CodebaseEnvironmentResponse)
     * to the standard EnvironmentResponse format expected by the codebase.
     *
     * Based on acquia-spec.json schemas:
     * - Standard Environment (v2 API): Has full application context, domains, balancer, etc.
     * - V3 Environment (Codebase Environment): Has codebase reference, simplified structure
     *
     * @param mixed $environment
     *   The environment object to transform.
     * @return \AcquiaCloudApi\Response\EnvironmentResponse
     *   The transformed environment response object.
     */
    public static function transform(mixed $environment): EnvironmentResponse
    {
        // If already an EnvironmentResponse, return as-is.
        if ($environment instanceof EnvironmentResponse) {
            return $environment;
        }

        // Convert the object to stdClass for EnvironmentResponse constructor.
        $environmentData = (object) [];

        // Copy over all properties that exist in the source environment.
        foreach (get_object_vars($environment) as $property => $value) {
            $environmentData->$property = $value;
        }

        // Handle CodebaseEnvironmentResponse (v3) specific property mappings.
        if (isset($environmentData->links) && !isset($environmentData->_links)) {
            $environmentData->_links = $environmentData->links;
        }

        // Map essential properties from CodebaseEnvironmentResponse to EnvironmentResponse expected format
        // EnvironmentResponse constructor sets uuid = id, so we need to ensure id contains the correct UUID.
        if (!isset($environmentData->id) && isset($environmentData->uuid)) {
            $environmentData->id = $environmentData->uuid;
        } elseif (isset($environmentData->uuid) && isset($environmentData->id)) {
            // If both exist, prefer uuid and override id with uuid value
            // since EnvironmentResponse constructor will set uuid = id.
            $environmentData->id = $environmentData->uuid;
        }

        // Transform v3 environment properties to v2 environment format.
        static::transformV3ToV2Properties($environmentData);

        return new EnvironmentResponse($environmentData);
    }

    /**
     * Transform v3 environment properties to match v2 environment schema.
     *
     * Based on acquia-spec.json:
     * - v3: Has 'id', 'name', 'label', 'description', 'status', 'flags', 'reference' (branch)
     * - v2: Expects 'id', 'label', 'name', 'application', 'domains', 'active_domain', etc.
     */
    private static function transformV3ToV2Properties(object $environmentData): void
    {
        // If neither id nor uuid exists, try to provide a sensible default.
        if (!isset($environmentData->id)) {
            $environmentData->id = '';
        }

        if (!isset($environmentData->label)) {
            $environmentData->label = $environmentData->name ?? 'Unknown Environment';
        }

        if (!isset($environmentData->name)) {
            $environmentData->name = $environmentData->label ?? 'unknown';
        }

        // Transform codebase reference to application format (v3 -> v2)
        if (!isset($environmentData->application)) {
            if (isset($environmentData->codebase_uuid)) {
                // CodebaseEnvironmentResponse doesn't preserve full codebase data,
                // so we use the environment label as a fallback for application name.
                $applicationName = $environmentData->label ?? 'Unknown Application';

                $environmentData->application = (object) [
                    'name' => $applicationName,
                    'uuid' => $environmentData->codebase_uuid,
                ];
            } else {
                $environmentData->application = (object) ['uuid' => '', 'name' => ''];
            }
        }

        // Set required v2 properties with sensible defaults.
        if (!isset($environmentData->domains)) {
            $environmentData->domains = [];
        }

        if (!isset($environmentData->active_domain)) {
            $environmentData->active_domain = '';
        }

        if (!isset($environmentData->default_domain)) {
            $environmentData->default_domain = '';
        }

        if (!isset($environmentData->image_url)) {
            $environmentData->image_url = null;
        }

        if (!isset($environmentData->ips)) {
            $environmentData->ips = [];
        }

        if (!isset($environmentData->region)) {
            $environmentData->region = null;
        }

        if (!isset($environmentData->balancer)) {
            $environmentData->balancer = '';
        }

        if (!isset($environmentData->status)) {
            $environmentData->status = 'active';
        }

        if (!isset($environmentData->type)) {
            $environmentData->type = 'drupal';
        }

        if (!isset($environmentData->platform)) {
            $environmentData->platform = 'cloud';
        }

        // Transform v3 'reference' (branch) to v2 'vcs' object.
        if (!isset($environmentData->vcs)) {
            $branch = $environmentData->reference ?? 'master';
            $environmentData->vcs = (object) [
                'branch' => $branch,
                'path' => $branch,
                // VCS URL is not available from v3 CodebaseEnvironmentResponse.
                'url' => '',
            ];
        }

        // Ensure flags exist with defaults.
        if (!isset($environmentData->flags)) {
            $environmentData->flags = (object) [];
        }

        $flags = $environmentData->flags;
        if (!isset($flags->production)) {
            $flags->production = false;
        }
        if (!isset($flags->cde)) {
            $flags->cde = false;
        }
        $environmentData->flags = $flags;

        if (!isset($environmentData->configuration)) {
            $environmentData->configuration = null;
        }

        if (!isset($environmentData->_links)) {
            $environmentData->_links = (object) [];
        }

        if (!isset($environmentData->artifact)) {
            $environmentData->artifact = null;
        }

        // Handle SSH URL property mapping (v3 vs v2 might use different property names)
        if (!isset($environmentData->ssh_url) && isset($environmentData->sshUrl)) {
            $environmentData->ssh_url = $environmentData->sshUrl;
        } elseif (isset($environmentData->ssh_url) && !isset($environmentData->sshUrl)) {
            $environmentData->sshUrl = $environmentData->ssh_url;
        }
    }
}
