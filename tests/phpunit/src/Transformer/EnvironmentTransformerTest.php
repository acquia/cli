<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Transformer;

use Acquia\Cli\Transformer\EnvironmentTransformer;
use AcquiaCloudApi\Response\EnvironmentResponse;
use PHPUnit\Framework\TestCase;

class EnvironmentTransformerTest extends TestCase
{
    public function testTransformWithAllProperties(): void
    {
        $codebaseEnv = (object)[
            'codebase' => (object)[
                'vcs_url' => 'https://github.com/acquia/repo.git',
            ],
            'flags' => ['new' => true],
            'id' => 'env-id',
            'label' => 'Development',
            'links' => ['self' => 'link'],
            'name' => 'dev',
            'properties' => [
                'active_domain' => 'active.example.com',
                'artifact' => 'artifact-info',
                'balancer' => 'balancer-url',
                'configuration' => ['php_version' => '8.1'],
                'default_domain' => 'default.example.com',
                'domains' => ['example.com'],
                'gardener' => 'gardener-name',
                'image_url' => 'https://image.url',
                'ips' => ['127.0.0.1'],
                'platform' => 'acsf',
                'region' => 'us-east-1',
                'ssh_url' => 'ssh://url',
                'type' => 'multisite',
            ],
            'reference' => 'main',
            'status' => 'active',
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        $this->assertInstanceOf(EnvironmentResponse::class, $env);
        $this->assertEquals('dev', $env->name);
        $this->assertEquals('main', $env->vcs->branch);
        $this->assertEquals('https://github.com/acquia/repo.git', $env->vcs->url);
        $this->assertEquals('multisite', $env->type);

        // Cover _links coalesce mutation.
        $this->assertIsObject($env->links);
        $this->assertObjectHasProperty('self', $env->links);
        $this->assertEquals('link', $env->links->self);
        $this->assertIsObject($env->flags);
        $this->assertObjectHasProperty('new', $env->flags);
        $this->assertTrue($env->flags->new);
        $this->assertEquals(['example.com'], $env->domains);
        $this->assertEquals('acsf', $env->platform);
        $this->assertEquals('balancer-url', $env->balancer);
        $this->assertEquals('active.example.com', $env->active_domain);
        $this->assertEquals('default.example.com', $env->default_domain);
        $this->assertEquals(['127.0.0.1'], $env->ips);
    }

    public function testTransformWithMissingOptionalFields(): void
    {
        $codebaseEnv = (object)[
            'flags' => null,
            'id' => 'env-id',
            'label' => 'Test Environment',
            'links' => null,
            'name' => 'test',
            'properties' => [],
            'status' => 'normal',
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        // Missing in properties.
        $this->assertEquals('', $env->active_domain);
        // Fallback if reference not set.
        $this->assertEquals('master', $env->vcs->branch);
        // vcs_url missing.
        $this->assertEquals('', $env->vcs->url);
        // Fallback to empty object.
        $this->assertEquals([], (array)$env->flags);
        // Fallback to empty object.
        $this->assertEquals([], (array)$env->links);
        $this->assertEquals('', $env->type);
        // Fallback to empty array.
        $this->assertEquals([], $env->domains);
        // Fallback to empty string.
        $this->assertEquals('', $env->platform);
        // Fallback to empty string.
        $this->assertEquals('', $env->balancer);
        $this->assertEquals('', $env->active_domain);
        $this->assertEquals('', $env->default_domain);
        $this->assertEquals([], $env->ips);
    }

    public function testTransformWithMissingCodebaseObject(): void
    {
        $codebaseEnv = (object)[
            'id' => 'env-id',
            'label' => 'Staging Environment',
            'name' => 'stage',
            'properties' => [],
            'reference' => 'feature-branch',
            'status' => 'normal',
            // 'codebase' is completely missing
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        $this->assertEquals('feature-branch', $env->vcs->branch);
        // No codebase, so url fallback.
        $this->assertEquals('', $env->vcs->url);
    }

    public function testTransformWithCodebaseButNoVcsUrl(): void
    {
        $codebaseEnv = (object)[
            // Codebase exists but no vcs_url.
            'codebase' => (object)[],
            'id' => 'env-id',
            'label' => 'Staging Environment',
            'name' => 'stage',
            'properties' => [],
            'reference' => 'release',
            'status' => 'normal',
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        $this->assertEquals('release', $env->vcs->branch);
        // Property exists check fails.
        $this->assertEquals('', $env->vcs->url);
    }

    public function testTransformFromCodeBase(): void
    {
        $codebase = (object)[
            'flags' => ['production' => true],
            'id' => 'codebase-123',
            'label' => 'My Codebase',
            'links' => ['self' => 'link'],
            'properties' => [
                'active_domain' => 'prod.example.com',
                'artifact' => 'build-123',
                'balancer' => 'load-balancer',
                'configuration' => ['memory' => '512M'],
                'default_domain' => 'example.com',
                'domains' => ['example.com', 'www.example.com'],
                'gardener' => 'auto-gardener',
                'image_url' => 'https://image.example.com',
                'ips' => ['192.168.1.1'],
                'platform' => 'drupal',
                'region' => 'us-west-2',
                'ssh_url' => 'ssh://example.com',
                'type' => 'standard',
            ],
            'reference' => 'main',
            'vcs_url' => 'https://github.com/acquia/example.git',
        ];

        $env = EnvironmentTransformer::transformFromCodeBase($codebase);

        $this->assertInstanceOf(EnvironmentResponse::class, $env);
        $this->assertEquals('codebase-123', $env->uuid);
        $this->assertEquals('My Codebase', $env->name);
        $this->assertEquals('My Codebase', $env->label);
        // Empty for codebase.
        $this->assertEquals('', $env->status);

        // VCS properties.
        $this->assertEquals('main', $env->vcs->branch);
        $this->assertEquals('main', $env->vcs->path);
        $this->assertEquals('https://github.com/acquia/example.git', $env->vcs->url);

        // Properties.
        $this->assertEquals('prod.example.com', $env->active_domain);
        $this->assertEquals('example.com', $env->default_domain);
        $this->assertEquals('https://image.example.com', $env->image_url);
        $this->assertEquals(['192.168.1.1'], $env->ips);
        $this->assertEquals(['example.com', 'www.example.com'], $env->domains);
        $this->assertEquals('us-west-2', $env->region);
        $this->assertEquals('drupal', $env->platform);
        $this->assertEquals('load-balancer', $env->balancer);
        $this->assertEquals('standard', $env->type);

        // Objects.
        $this->assertEquals((object)['memory' => '512M'], $env->configuration);
        $this->assertEquals((object)['production' => true], $env->flags);
        $this->assertEquals((object)['self' => 'link'], $env->links);
        // Empty object.
        $this->assertEquals((object)[], $env->application);
    }
}
