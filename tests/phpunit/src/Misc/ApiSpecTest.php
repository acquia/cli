<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class ApiSpecTest extends TestCase {

  public function testApiSpec(): void {
    $apiSpecFile = Path::canonicalize(__DIR__ . '/../../../../assets/acquia-spec.yaml');
    $this->assertFileExists($apiSpecFile);
    $apiSpec = file_get_contents($apiSpecFile);
    $this->assertStringNotContainsString('x-internal', $apiSpec);
    $this->assertStringNotContainsString('cloud.acquia.dev', $apiSpec);
    $this->assertStringNotContainsString('network.acquia-sites.com', $apiSpec);
  }

}
