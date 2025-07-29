<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Transformer;

use Acquia\Cli\Transformer\EnvironmentTransformer;
use AcquiaCloudApi\Response\CodebaseEnvironmentResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EnvironmentTransformer.
 */
class EnvironmentTransformerTest extends TestCase
{
    /**
     * Test that EnvironmentResponse objects are returned as-is.
     */
    public function testTransformEnvironmentResponseReturnsItself(): void
    {
        $environmentData = (object) [
            'active_domain' => 'example.com',
            'application' => (object) ['uuid' => 'app-123', 'name' => 'Test App'],
            'artifact' => null,
            'balancer' => 'test-balancer',
            'configuration' => null,
            'default_domain' => 'example.com',
            'domains' => ['example.com'],
            'flags' => (object) [],
            'id' => 'env-123',
            'image_url' => null,
            'ips' => [],
            'label' => 'Dev Environment',
            'name' => 'dev',
            'platform' => 'drupal',
            'region' => null,
            'ssh_url' => 'user@example.com',
            'status' => 'active',
            'type' => 'drupal',
            'uuid' => '12345-67890',
            'vcs' => (object) ['type' => 'git'],
            '_links' => (object) [],
        ];

        $environmentResponse = new EnvironmentResponse($environmentData);
        $result = EnvironmentTransformer::transform($environmentResponse);

        $this->assertSame($environmentResponse, $result);
    }

    /**
     * Test transforming a CodebaseEnvironmentResponse (v3) to EnvironmentResponse (v2).
     */
    public function testTransformCodebaseEnvironmentResponseToEnvironmentResponse(): void
    {
        $codebaseEnvironmentData = (object) [
            'codebase' => (object) [
                'id' => 'codebase-456',
                'name' => 'Test Codebase',
                'vcs_url' => 'git@github.com:example/repo.git',
            ],
            'code_switch_status' => 'IDLE',
            'description' => 'Development environment for testing',
            'flags' => (object) ['production' => false, 'cde' => true],
            'id' => 'cbe-123',
            'label' => 'Development Environment',
            'name' => 'dev',
            'properties' => [],
            'reference' => 'main',
            'status' => 'active',
            '_links' => (object) ['self' => (object) ['href' => '/environments/cbe-123']],
        ];

        $codebaseEnvironment = new CodebaseEnvironmentResponse($codebaseEnvironmentData);
        $result = EnvironmentTransformer::transform($codebaseEnvironment);

        $this->assertInstanceOf(EnvironmentResponse::class, $result);
        $this->assertEquals('cbe-123', $result->uuid);
        $this->assertEquals('Development Environment', $result->label);
        $this->assertEquals('dev', $result->name);
        $this->assertEquals('active', $result->status);
        $this->assertEquals('drupal', $result->type);
        $this->assertEquals('cloud', $result->platform);
        $this->assertEquals('', $result->balancer);
        $this->assertEquals('codebase-456', $result->application->uuid);
        $this->assertEquals('Development Environment', $result->application->name);
        // VCS URL not preserved by CodebaseEnvironmentResponse.
        $this->assertEquals('', $result->vcs->url);
        $this->assertEquals('main', $result->vcs->path);
        $this->assertEquals('main', $result->vcs->branch);
    }

    /**
     * Test transforming a generic object to EnvironmentResponse.
     */
    public function testTransformGenericObjectToEnvironmentResponse(): void
    {
        $mockEnvironment = (object) [
            'codebase_uuid' => 'codebase-123',
            'id' => 'env-123',
            'label' => 'Dev Environment',
            'name' => 'dev',
            'ssh_url' => 'user@example.com',
            'uuid' => '12345-67890',
        ];

        $result = EnvironmentTransformer::transform($mockEnvironment);

        $this->assertInstanceOf(EnvironmentResponse::class, $result);
        $this->assertEquals('12345-67890', $result->uuid);
        $this->assertEquals('Dev Environment', $result->label);
        $this->assertEquals('dev', $result->name);
        $this->assertEquals('user@example.com', $result->sshUrl);
        $this->assertEquals('', $result->balancer);
        $this->assertEquals('drupal', $result->type);
        $this->assertEquals('cloud', $result->platform);
    }

    /**
     * Test transforming a minimal object with default values.
     */
    public function testTransformMinimalObjectWithDefaults(): void
    {
        $mockEnvironment = (object) [
            'name' => 'minimal',
        ];

        $result = EnvironmentTransformer::transform($mockEnvironment);

        $this->assertInstanceOf(EnvironmentResponse::class, $result);
        $this->assertEquals('minimal', $result->name);
        $this->assertEquals('minimal', $result->label);
        $this->assertEquals('', $result->uuid);
        $this->assertEquals('active', $result->status);
        $this->assertEquals('drupal', $result->type);
        $this->assertEquals('cloud', $result->platform);
        $this->assertEquals('', $result->balancer);
        $this->assertEmpty($result->domains);
        $this->assertEmpty($result->ips);
        $this->assertInstanceOf(\stdClass::class, $result->application);
        $this->assertInstanceOf(\stdClass::class, $result->vcs);
        $this->assertInstanceOf(\stdClass::class, $result->flags);
    }

    /**
     * Test SSH URL handling between ssh_url and sshUrl properties.
     */
    public function testSshUrlHandling(): void
    {
        // Test converting ssh_url to sshUrl.
        $mockEnvironment1 = (object) [
            'name' => 'test1',
            'ssh_url' => 'user@server1.example.com',
        ];

        $result1 = EnvironmentTransformer::transform($mockEnvironment1);
        $this->assertEquals('user@server1.example.com', $result1->sshUrl);

        // Test converting sshUrl to ssh_url.
        $mockEnvironment2 = (object) [
            'name' => 'test2',
            'sshUrl' => 'user@server2.example.com',
        ];

        $result2 = EnvironmentTransformer::transform($mockEnvironment2);
        $this->assertEquals('user@server2.example.com', $result2->sshUrl);
    }

    /**
     * Test v3 to v2 transformation specific features.
     */
    public function testV3ToV2Transformation(): void
    {
        // V3 environment structure (CodebaseEnvironment)
        $v3Environment = (object) [
            'codebase_uuid' => 'cb-789',
            'description' => 'Staging environment for testing',
            'flags' => (object) [
                'cde' => true,
                'production' => false,
            ],
            'id' => 'v3-env-456',
            'label' => 'Staging Environment',
            'name' => 'staging',
            'properties' => [],
            // V3 uses 'reference' for branch.
            'reference' => 'develop',
            'status' => 'normal',
        ];

        $result = EnvironmentTransformer::transform($v3Environment);

        $this->assertInstanceOf(EnvironmentResponse::class, $result);

        // Test V3 -> V2 property transformations.
        $this->assertEquals('v3-env-456', $result->uuid);
        $this->assertEquals('Staging Environment', $result->label);
        $this->assertEquals('staging', $result->name);
        $this->assertEquals('normal', $result->status);

        // Test application object creation from codebase_uuid.
        $this->assertEquals('cb-789', $result->application->uuid);
        $this->assertEquals('Staging Environment', $result->application->name);

        // Test VCS object creation from reference.
        $this->assertEquals('develop', $result->vcs->path);
        $this->assertEquals('develop', $result->vcs->branch);
        $this->assertEquals('', $result->vcs->url);

        // Test flag preservation and defaults.
        $this->assertFalse($result->flags->production);
        $this->assertTrue($result->flags->cde);

        // Test V2 required properties have defaults.
        $this->assertEquals('drupal', $result->type);
        $this->assertEquals('cloud', $result->platform);
        $this->assertEquals('', $result->balancer);
        $this->assertIsArray($result->domains);
        $this->assertEmpty($result->domains);
    }
}
