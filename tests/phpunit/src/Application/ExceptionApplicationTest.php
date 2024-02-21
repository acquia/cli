<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;

/**
 * Tests exceptions rewritten by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 */
class ExceptionApplicationTest extends ApplicationTestBase {

  /**
   * @group serial
   */
  public function testInvalidApiCredentials(): void {
    $this->setInput([
      'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
      'command' => 'aliases',
    ]);
    $this->mockUnauthorizedRequest();
    $buffer = $this->runApp();
    // This is sensitive to the display width of the test environment, so that's fun.
    self::assertStringContainsString('Your Cloud Platform API credentials are invalid.', $buffer);
  }

  /**
   * @group serial
   */
  public function testApiError(): void {
    $this->setInput([
      'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
      'command' => 'aliases',
    ]);
    $this->mockApiError();
    $buffer = $this->runApp();
    self::assertStringContainsString('Cloud Platform API returned an error:', $buffer);
  }

  /**
   * @group serial
   */
  public function testNoAvailableIdes(): void {
    $this->setInput([
      'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
      'command' => 'aliases',
    ]);
    $this->mockNoAvailableIdes();
    $buffer = $this->runApp();
    self::assertStringContainsString('Delete an existing IDE', $buffer);
  }

  /**
   * @group serial
   */
  public function testInvalidEnvironmentUuid(): void {
    $this->mockRequest('getAccount');
    $this->mockRequest('getApplications');
    $this->setInput([
      'command' => 'log:tail',
      'environmentId' => 'aoeuth.aoeu',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be a site alias.', $buffer);
  }

  /**
   * @group serial
   */
  public function testMissingApplicationUuid(): void {
    $this->setInput([
      'command' => 'ide:open',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('Could not determine Cloud Application.', $buffer);
  }

  /**
   * @group serial
   */
  public function testInvalidApplicationUuid(): void {
    $this->mockRequest('getAccount');
    $this->mockRequest('getApplications');
    $this->setInput([
      'applicationUuid' => 'aoeuthao',
      'command' => 'ide:open',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('An alias consists of an application name', $buffer);
  }

}
