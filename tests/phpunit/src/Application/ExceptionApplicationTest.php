<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;
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

  /**
   * @throws \Exception
   * @group serial
   */
  public function testPreScripts(): void {
    $json = [
      'scripts' => [
        'pre-acli-hello-world' => [
          'echo "good morning world"'
        ]
      ]
    ];
    file_put_contents(
      Path::join($this->projectDir, 'composer.json'),
      json_encode($json, JSON_THROW_ON_ERROR)
    );
    $this->mockAccountRequest();
    $this->setInput([
          'command' => 'hello-world',
      ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('pre-acli-hello-world', $buffer);
  }

  /**
   * @throws \Exception
   * @group serial
   */
  public function testPostScripts(): void {
    $json = [
      'scripts' => [
        'post-acli-hello-world' => [
          'echo "goodbye world"'
        ]
      ]
    ];
    file_put_contents(
      Path::join($this->projectDir, 'composer.json'),
      json_encode($json, JSON_THROW_ON_ERROR)
    );
    $this->mockAccountRequest();
    $this->setInput([
          'command' => 'hello-world',
      ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('post-acli-hello-world', $buffer);
  }

  /**
   * @throws \Exception
   * @group serial
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
   * @group serial
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
   * @group serial
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
   * @group serial
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
   * @group serial
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
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   * @group serial
   */
  public function testInvalidApplicationUuid(): void {
    $this->mockAccountRequest();
    $this->mockApplicationsRequest();
    $this->setInput([
      'command' => 'ide:open',
      'applicationUuid' => 'aoeuthao',
    ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('[ERROR] Use a unique application alias: devcloud:devcloud2, devcloud:devcloud3', $buffer);
    self::assertStringContainsString('Multiple applications match the alias *:aoeuthao', $buffer);
    $helpText = "[help] The applicationUuid argument must be a valid UUID or unique                  \n        application alias accessible to your Cloud Platform user.                    \n                                                                                     \n        An alias consists of an application name optionally prefixed with a hosting realm,\n        e.g. myapp or                                                                \n        prod.myapp.                                                                  \n";
    self::assertStringContainsString($helpText, $buffer);
  }

}
