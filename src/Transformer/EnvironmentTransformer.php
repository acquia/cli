<?php

declare(strict_types=1);

namespace Acquia\Cli\Transformer;

use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use stdClass;

class EnvironmentTransformer
{
    /**
     * Transform a CodebaseEnvironmentResponse object to an EnvironmentResponse object.
     */
    public static function transform(mixed $codebaseEnv): EnvironmentResponse
    {
        $env = new \stdClass();
        // Core fields.
        $env->id = $codebaseEnv->id;
        $env->uuid = $codebaseEnv->id;
        $env->name = $codebaseEnv->name;
        $env->label = $codebaseEnv->label;
        $env->status = $codebaseEnv->status;
        $env->ssh_url =  $codebaseEnv->ssh_url;

        if (isset($codebaseEnv->properties) && is_object($codebaseEnv->properties)) {
            $codebaseEnv->properties = (array)$codebaseEnv->properties;
        }
        // Domains, network, etc.
        $env->active_domain = $codebaseEnv->properties['active_domain'] ?? '';
        $env->default_domain = $codebaseEnv->properties['default_domain'] ?? '';
        $env->image_url = $codebaseEnv->properties['image_url'] ?? null;
        $env->ips = $codebaseEnv->properties['ips'] ?? [];
        $env->domains = $codebaseEnv->properties['domains'] ?? [];
        $env->region = $codebaseEnv->properties['region'] ?? null;
        $env->platform = $codebaseEnv->properties['platform'] ?? 'MEO';
        $env->balancer = $codebaseEnv->properties['balancer'] ?? '';
        $env->artifact = (object)($codebaseEnv->properties['artifact'] ?? null);
        $env->gardener = (object)($codebaseEnv->properties['gardener'] ?? null);

        // Application context (not present in CodebaseEnvironment, set to empty object)
        $env->application = (object) [];

        // VCS logic.
        $branch = $codebaseEnv->reference ?? 'master';
        $vcsUrl = '';
        if (
            isset($codebaseEnv->codebase) &&
            is_object($codebaseEnv->codebase) &&
            property_exists($codebaseEnv->codebase, 'vcs_url')
        ) {
            $vcsUrl = $codebaseEnv->codebase->vcs_url;
        }
        $env->vcs = (object) [
            'branch' => $branch,
            'path' => $branch,
            'url' => $vcsUrl,
        ];

        // Optional config.
        $env->configuration = (object) [];
        $env->configuration->php = (object) ($codebaseEnv->properties ?? []);

        // Cast for mutation safety.
        $env->flags = (object) ($codebaseEnv->flags ?? []);
        $env->_links = (object) ($codebaseEnv->links ?? []);
        $env->type = $codebaseEnv->properties['type'] ?? '';
        // Now instantiate EnvironmentResponse.
        return new EnvironmentResponse($env);
    }

    /**
     * Transform a SiteInstanceDatabaseResponse object to a DatabaseResponse object.
     */
    public static function transformSiteInstanceDatabase(mixed $siteInstanceDb): DatabaseResponse
    {
        $db = new \stdClass();
        $db->id = $siteInstanceDb->databaseName;
        $db->name = $siteInstanceDb->databaseName;
        $db->user_name = $siteInstanceDb->databaseUserName;
        $db->password = $siteInstanceDb->databasePassword;
        $db->url = null;
        $db->db_host = $siteInstanceDb->databaseHost;
        $db->ssh_host = null;
        $db->flags = (object) ['role' => $siteInstanceDb->databaseRole];
        $db->environment = new stdClass();
        return new DatabaseResponse($db);
    }
}
