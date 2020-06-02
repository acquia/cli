<?php

namespace Acquia\Cli\Tests;

use PHPUnit\Framework\TestCase;
use Webmozart\PathUtil\Path;

/**
 * Class ApiSpecTest
 */
class ApiSpecTest extends TestCase {

  public function testApiSpec(): void {
    $api_spec_file = Path::canonicalize(__DIR__ . '/../../../assets/acquia-spec.yaml');
    $this->assertFileExists($api_spec_file);
    $api_spec = file_get_contents($api_spec_file);
    $this->assertStringNotContainsString('x-internal', $api_spec);
    $this->assertStringNotContainsString('cloud.acquia.dev', $api_spec);
    $this->assertStringNotContainsString(' network.acquia-sites.com', $api_spec);
  }

}
