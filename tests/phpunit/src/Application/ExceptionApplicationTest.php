<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\Filesystem\Path;

/**
 * Tests exceptions rewritten by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 *
 * @package Acquia\Cli\Tests\Application
 */
class ExceptionApplicationTest extends ApplicationTestBase {

  public function setUp($output = NULL): void {
    vfsStream::newFile('composer.json')
      ->at($this->root)
      ->withContent(file_get_contents(Path::join($this->realFixtureDir, 'composer.json')));
  }

  /**
   * @throws \Exception
   */
  public function testPreScripts(): void {
    $this->mockAccountRequest();
    $this->setInput([
          'command' => 'hello-world',
      ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('pre-acli-hello-world', $buffer);
  }

  /**
   * @throws \Exception
   */
  public function testPostScripts(): void {
    $this->mockAccountRequest();
    $this->setInput([
          'command' => 'hello-world',
      ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('post-acli-hello-world', $buffer);
  }

  /**
   * @throws \Exception
   */
  public function testInvalidApiCreds(): void {
    $this->setInput([
      'command' => 'aliases',
      'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
    ]);
    $this->mockUnauthorizedRequest();
    $buffer = $this->runApp();
    // This is sensitive to the display width of the test environment, so that's fun.
    self::assertStringContainsString('Your Cloud Platform API credentials are invalid.', $buffer);
  }

  /**
   * @throws \Exception
   */
  public function testApiError(): void {
    $this->setInput([
      'command' => 'aliases',
      'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
    ]);
    $this->mockApiError();
    $buffer = $this->runApp();
    self::assertStringContainsString('Cloud Platform API returned an error:', $buffer);
  }

  /**
   * @throws \Exception
   */
  public function testNoAvailableIdes(): void {
    $this->setInput([
      'command' => 'aliases',
      'applicationUuid' => '2ed281d4-9dec-4cc3-ac63-691c3ba002c2',
    ]);
    $this->mockNoAvailableIdes();
    $buffer = $this->runApp();
    self::assertStringContainsString('Delete an existing IDE', $buffer);
  }

  /**
   * @throws \Exception
   */
  public function testMissingEnvironmentUuid(): void {
    $this->setInput([
      'command' => 'log:tail',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be a site alias.', $buffer);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   */
  public function testInvalidEnvironmentUuid(): void {
    $this->mockAccountRequest();
    $this->mockApplicationsRequest();
    $this->setInput([
      'command' => 'log:tail',
      'environmentId' => 'aoeuth.aoeu',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be a site alias.', $buffer);
  }

  /**
   * @throws \Exception
   */
  public function testMissingApplicationUuid(): void {
    $this->setInput([
      'command' => 'ide:open',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('Could not determine Cloud Application.', $buffer);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   */
  public function testInvalidApplicationUuid(): void {
    $this->mockAccountRequest();
    $this->mockApplicationsRequest();
    $this->setInput([
      'command' => 'ide:open',
      'applicationUuid' => 'aoeuthao',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('An alias consists of an application name', $buffer);
  }

}
