<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\DataStore;

use Acquia\Cli\DataStore\JsonDataStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Uses a real temp directory rather than vfsStream because vfsStream does not
 * faithfully emulate chmod() on all PHP versions.
 */
class JsonDataStoreTest extends TestCase
{
    private string $tempDir;

    private string $filepath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/acli_json_datastore_' . uniqid();
        (new Filesystem())->mkdir($this->tempDir);
        $this->filepath = $this->tempDir . '/cloud_api.conf';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
        parent::tearDown();
    }

    public function testDumpRestrictsFilePermissions(): void
    {
        $store = new JsonDataStore($this->filepath);
        $store->set('acli_key', 'test-key');
        $store->dump();

        clearstatcache(true, $this->filepath);
        $this->assertTrue(is_readable($this->filepath));
        $this->assertSame(0600, fileperms($this->filepath) & 0777);
        $this->assertSame('test-key', json_decode(file_get_contents($this->filepath), true)['acli_key']);
    }

    public function testDumpRestrictsPermissionsOnExistingFile(): void
    {
        file_put_contents($this->filepath, json_encode(['acli_key' => 'test-key'], JSON_THROW_ON_ERROR));
        chmod($this->filepath, 0644);

        $store = new JsonDataStore($this->filepath);
        $store->dump();

        clearstatcache(true, $this->filepath);
        $this->assertSame(0600, fileperms($this->filepath) & 0777);
    }
}
