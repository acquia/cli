<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\ConnectorFactory;
use Acquia\Cli\RefreshTokenConnector;
use Acquia\Cli\Tests\TestBase;

/**
 * Class RefreshTokenTest.
 */
class RefreshTokenConnectorTest extends TestBase {

  /**
   * @var string
   */
  private $refreshToken;

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->refreshToken = 'testrefeshtoken';
    putenv('ACLI_REFRESH_TOKEN=' . $this->refreshToken);
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('ACLI_REFRESH_TOKEN');
  }

  public function testRefreshToken() {
    self::assertEquals($this->refreshToken, $this->cloudCredentials->getCloudRefreshToken());
    $connector_factory = new ConnectorFactory(
      [
        'key' => $this->cloudCredentials->getCloudKey(),
        'secret' => $this->cloudCredentials->getCloudSecret(),
        'refreshToken' => $this->cloudCredentials->getCloudRefreshToken(),
      ],
      $this->cloudCredentials->getBaseUri());
    $connector = $connector_factory->createConnector();
    self::assertInstanceOf(RefreshTokenConnector::class, $connector);
  }

}
