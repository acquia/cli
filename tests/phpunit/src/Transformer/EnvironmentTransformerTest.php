<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Transformer;

use Acquia\Cli\Transformer\EnvironmentTransformer;
use AcquiaCloudApi\Response\BackupResponse;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use PHPUnit\Framework\TestCase;

/**
 * @group serial
 */
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
                'default_domain' => 'default.example.com',
                'domains' => ['example.com'],
                'gardener' => 'gardener-name',
                'image_url' => 'https://image.url',
                'ips' => ['127.0.0.1'],
                'platform' => 'acsf',
                'region' => 'us-east-1',
                'type' => 'multisite',
            ],
            'reference' => 'main',
            'ssh_url' => 'site.dev@sitedev.ssh.hosted.acquia-sites.com',
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
        $this->assertIsObject($env->configuration->php);
        $this->assertEquals($codebaseEnv->properties, (array)$env->configuration->php);
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
            'ssh_url' => 'site.dev@sitedev.ssh.hosted.acquia-sites.com',
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
        $this->assertEquals('MEO', $env->platform);
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
            'ssh_url' => 'site.dev@sitedev.ssh.hosted.acquia-sites.com',
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
            'ssh_url' => 'site.dev@sitedev.ssh.hosted.acquia-sites.com',
            'status' => 'normal',
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        $this->assertEquals('release', $env->vcs->branch);
        // Property exists check fails.
        $this->assertEquals('', $env->vcs->url);
    }

    public function testTransformWithPropertiesAsObject(): void
    {
        $codebaseEnv = (object)[
            'id' => 'env-id',
            'label' => 'Env with Object Props',
            'name' => 'obj-props',
            'properties' => (object)[
                'active_domain' => 'object.example.com',
                'domains' => ['obj.com'],
            ],
            'ssh_url' => 'site.dev@sitedev.ssh.hosted.acquia-sites.com',
            'status' => 'active',
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        // Ensure properties were cast from object → array properly.
        $this->assertEquals('object.example.com', $env->active_domain);
        $this->assertEquals(['obj.com'], $env->domains);
    }
    public function testSshUrlPrefersPropertiesOverVcsUrl(): void
    {
        $codebaseEnv = (object)[
            'codebase' => (object)['vcs_url' => 'https://github.com/acquia/repo.git'],
            'id' => 'env-id',
            'label' => 'Dev',
            'name' => 'dev',
            'ssh_url' => 'site.dev@sitedev.ssh.hosted.acquia-sites.com',
            'status' => 'active',
        ];

        $env = EnvironmentTransformer::transform($codebaseEnv);

        // If the mutant is present ($env->ssh_url = $vcsUrl ?? $env->ssh_url),
        // this would wrongly become the VCS URL. This assertion kills the mutant.
        $this->assertSame('site.dev@sitedev.ssh.hosted.acquia-sites.com', $env->sshUrl);
        $this->assertSame('https://github.com/acquia/repo.git', $env->vcs->url);
    }

    public function testTransformSiteInstanceDatabase(): void
    {
        $siteInstanceDb = (object)[
            'databaseHost' => 'test.example.com',
            'databaseName' => 'test_db',
            'databasePassword' => 'test_password',
            'databaseRole' => 'primary',
            'databaseUserName' => 'test_user',
        ];

        $databaseResponse = EnvironmentTransformer::transformSiteInstanceDatabase($siteInstanceDb);

        $this->assertInstanceOf(DatabaseResponse::class, $databaseResponse);
        $this->assertEquals('test_db', $databaseResponse->id);
        $this->assertEquals('test_db', $databaseResponse->name);
        $this->assertEquals('test_user', $databaseResponse->user_name);
        $this->assertEquals('test_password', $databaseResponse->password);
        $this->assertNull($databaseResponse->url);
        $this->assertEquals('test.example.com', $databaseResponse->db_host);
        $this->assertNull($databaseResponse->ssh_host);
        $this->assertIsObject($databaseResponse->flags);
        $this->assertEquals('primary', $databaseResponse->flags->role);
        $this->assertFalse($databaseResponse->flags->default);
        $this->assertIsObject($databaseResponse->environment);
    }

    public function testTransformSiteInstanceDatabaseBackup(): void
    {
        $backupData = (object)[
            'created_at' => '2025-09-08T10:00:00Z',
            'database_id' => 'db-456',
            'id' => '123',
            'links' => (object)[
                'self' => 'backup-link',
            ],
            '_links' => (object)[
                'environment' => (object)[
                    'href' => 'https://staging.cloud.acquia.com/api/environments/5997a125-e73e-495e-9951-11d9ea9c1b69',
                ],
            ],
        ];

        $backupResponse = EnvironmentTransformer::transformSiteInstanceDatabaseBackup($backupData);

        $this->assertInstanceOf(BackupResponse::class, $backupResponse);
        $this->assertEquals(123, $backupResponse->id);
        $this->assertEquals('2025-09-08T10:00:00Z', $backupResponse->startedAt);
        $this->assertEquals('2025-09-08T10:00:00Z', $backupResponse->completedAt);
        $this->assertEquals('daily', $backupResponse->type);
        $this->assertIsObject($backupResponse->database);
        $this->assertEquals('db-456', $backupResponse->database->id);
        $this->assertIsObject($backupResponse->flags);
        $this->assertFalse($backupResponse->flags->deleted);
        $this->assertIsObject($backupResponse->environment);
        $this->assertEquals('5997a125-e73e-495e-9951-11d9ea9c1b69', $backupResponse->environment->id);
    }

    public function testTransformSiteInstanceDatabaseBackupWithoutEnvironmentLink(): void
    {
        $backupData = (object)[
            'created_at' => '2025-09-08T11:00:00Z',
            'database_id' => 'db-012',
            'id' => '789',
            'links' => (object)[
                'self' => 'backup-link-2',
            ],
            '_links' => (object)[
                // No environment link.
            ],
        ];

        $backupResponse = EnvironmentTransformer::transformSiteInstanceDatabaseBackup($backupData);

        $this->assertInstanceOf(BackupResponse::class, $backupResponse);
        $this->assertEquals(789, $backupResponse->id);
        $this->assertIsObject($backupResponse->environment);
        $this->assertNull($backupResponse->environment->id);
    }

    public function testTransformSiteInstanceDatabaseBackupCastsIdToInteger(): void
    {
        $backupData = (object)[
            'created_at' => '2025-09-08T12:00:00Z',
            'database_id' => 'db-cast-test',
            // String ID to test casting.
            'id' => '456',
            'links' => (object)[
                'self' => 'cast-test-link',
            ],
            '_links' => (object)[],
        ];

        $backupResponse = EnvironmentTransformer::transformSiteInstanceDatabaseBackup($backupData);

        // This assertion kills the CastInt mutation by ensuring the ID is specifically an integer.
        $this->assertSame(456, $backupResponse->id);
        $this->assertIsInt($backupResponse->id);
    }

    public function testTransformSiteInstanceDatabaseBackupDatabaseLinksStructure(): void
    {
        $linksObject = (object)[
            'download' => 'download-link',
            'self' => 'database-link',
        ];

        $backupData = (object)[
            'created_at' => '2025-09-08T13:00:00Z',
            'database_id' => 'db-links-test',
            'id' => '999',
            'links' => $linksObject,
            '_links' => (object)[],
        ];

        $backupResponse = EnvironmentTransformer::transformSiteInstanceDatabaseBackup($backupData);

        // This assertion kills the ArrayItem mutation by verifying the _links structure.
        $this->assertIsObject($backupResponse->database);
        $this->assertEquals('db-links-test', $backupResponse->database->id);
        $this->assertSame($linksObject, $backupResponse->database->_links);
        $this->assertObjectHasProperty('self', $backupResponse->database->_links);
        $this->assertEquals('database-link', $backupResponse->database->_links->self);

        $backupData = (object)[
            'created_at' => '2025-09-08T13:00:00Z',
            'database_id' => 'db-links-test',
            'id' => 'a1',
            'links' => $linksObject,
            '_links' => (object)[],
        ];

        $backupResponse = EnvironmentTransformer::transformSiteInstanceDatabaseBackup($backupData);

        // This assertion kills the ArrayItem mutation by verifying the _links structure.
        $this->assertIsObject($backupResponse->database);
        $this->assertEquals('db-links-test', $backupResponse->database->id);
        $this->assertSame($linksObject, $backupResponse->database->_links);
        $this->assertObjectHasProperty('self', $backupResponse->database->_links);
        $this->assertEquals('database-link', $backupResponse->database->_links->self);
    }
}
