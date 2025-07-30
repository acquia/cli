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
    public function testNameFallbacksToLabelWhenNameMissing(): void
    {
        $mockEnvironment = (object) [
            'label' => 'Label Only Env',
        // No 'name'.
        ];

        $result = EnvironmentTransformer::transform($mockEnvironment);

        // ✅ This will fail on the mutant
        $this->assertEquals('Label Only Env', $result->name);
    }

    /**
     * Test links property handling between links and _links.
     */
    public function testLinksHandling(): void
    {
        // Test when links exists but _links doesn't.
        $mockEnvironment1 = (object) [
            'id' => 'env1',
            'links' => (object) ['self' => '/api/environments/123'],
            'name' => 'test1',
        ];

        $result1 = EnvironmentTransformer::transform($mockEnvironment1);
        $this->assertEquals((object) ['self' => '/api/environments/123'], $result1->links);

        // Test when both links and _links exist - should not overwrite _links.
        $mockEnvironment2 = (object) [
            'id' => 'env2',
            'links' => (object) ['self' => '/api/environments/123'],
            'name' => 'test2',
            '_links' => (object) ['existing' => '/api/existing'],
        ];

        $result2 = EnvironmentTransformer::transform($mockEnvironment2);
        $this->assertEquals((object) ['existing' => '/api/existing'], $result2->links);

        // Test when neither links nor _links exist.
        $mockEnvironment3 = (object) [
            'id' => 'env3',
            'name' => 'test3',
        ];

        $result3 = EnvironmentTransformer::transform($mockEnvironment3);
        $this->assertInstanceOf(\stdClass::class, $result3->links);
    }

    /**
     * Test id/uuid property handling.
     */
    public function testIdUuidHandling(): void
    {
        // Test when uuid exists but id doesn't.
        $mockEnvironment1 = (object) [
            'name' => 'test1',
            'uuid' => 'uuid-123',
        ];

        $result1 = EnvironmentTransformer::transform($mockEnvironment1);
        $this->assertEquals('uuid-123', $result1->uuid);

        // Test when both uuid and id exist - should use uuid for id.
        $mockEnvironment2 = (object) [
            'id' => 'id-789',
            'name' => 'test2',
            'uuid' => 'uuid-456',
        ];

        $result2 = EnvironmentTransformer::transform($mockEnvironment2);
        $this->assertEquals('uuid-456', $result2->uuid);

        // Test when neither uuid nor id exist.
        $mockEnvironment3 = (object) [
            'name' => 'test3',
        ];

        $result3 = EnvironmentTransformer::transform($mockEnvironment3);
        $this->assertEquals('', $result3->uuid);
    }

    /**
     * Test flags property handling with various combinations.
     */
    public function testFlagsHandling(): void
    {
        // Test when flags doesn't exist.
        $mockEnvironment1 = (object) [
            'id' => 'env1',
            'name' => 'test1',
        ];

        $result1 = EnvironmentTransformer::transform($mockEnvironment1);
        $this->assertFalse($result1->flags->production);
        $this->assertFalse($result1->flags->cde);

        // Test when flags exists but production doesn't.
        $mockEnvironment2 = (object) [
            'flags' => (object) ['cde' => true],
            'id' => 'env2',
            'name' => 'test2',
        ];

        $result2 = EnvironmentTransformer::transform($mockEnvironment2);
        $this->assertFalse($result2->flags->production);
        $this->assertTrue($result2->flags->cde);

        // Test when flags exists with production already set.
        $mockEnvironment3 = (object) [
            'flags' => (object) ['production' => true, 'cde' => false],
            'id' => 'env3',
            'name' => 'test3',
        ];

        $result3 = EnvironmentTransformer::transform($mockEnvironment3);
        $this->assertTrue($result3->flags->production);
        $this->assertFalse($result3->flags->cde);
    }

    /**
     * Test application property handling with various combinations.
     */
    public function testApplicationHandling(): void
    {
        // Test when codebase_uuid exists.
        $mockEnvironment1 = (object) [
            'codebase_uuid' => 'cb-123',
            'id' => 'env1',
            'label' => 'Test Environment 1',
            'name' => 'test1',
        ];

        $result1 = EnvironmentTransformer::transform($mockEnvironment1);
        $this->assertEquals('cb-123', $result1->application->uuid);
        $this->assertEquals('Test Environment 1', $result1->application->name);

        // Test when codebase_uuid doesn't exist.
        $mockEnvironment2 = (object) [
            'id' => 'env2',
            'label' => 'Test Environment 2',
            'name' => 'test2',
        ];

        $result2 = EnvironmentTransformer::transform($mockEnvironment2);
        $this->assertEquals('', $result2->application->uuid);
        $this->assertEquals('', $result2->application->name);

        // Test when application already exists.
        $mockEnvironment3 = (object) [
            'application' => (object) ['uuid' => 'existing-uuid', 'name' => 'Existing App'],
            'codebase_uuid' => 'cb-456',
            'id' => 'env3',
            'label' => 'Test Environment 3',
            'name' => 'test3',
        ];

        $result3 = EnvironmentTransformer::transform($mockEnvironment3);
        $this->assertEquals('existing-uuid', $result3->application->uuid);
        $this->assertEquals('Existing App', $result3->application->name);
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
    public function testVcsUrlIsPreservedWhenPresent(): void
    {
        $mockEnvironment = (object) [
            'codebase' => (object) [
                'vcs_url' => 'https://git.example.com/repo.git',
            ],
            'reference' => 'main',
        ];

        $result = EnvironmentTransformer::transform($mockEnvironment);
        $this->assertEquals('https://git.example.com/repo.git', $result->vcs->url);
    }
}
