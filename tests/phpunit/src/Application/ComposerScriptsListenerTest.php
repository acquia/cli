<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;
use Symfony\Component\Filesystem\Path;

/**
 * Tests Composer hooks handled by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 */
class ComposerScriptsListenerTest extends ApplicationTestBase {

  /**
   * @group serial
   * @covers \Acquia\Cli\EventListener\ComposerScriptsListener::onConsoleCommand
   */
  public function testPreScripts(): void {
    $json = [
      'scripts' => [
        'pre-acli-hello-world' => [
          'echo "good morning world"',
        ],
      ],
    ];
    file_put_contents(
      Path::join($this->projectDir, 'composer.json'),
      json_encode($json, JSON_THROW_ON_ERROR)
    );
    $this->mockRequest('getAccount');
    $this->setInput([
          'command' => 'hello-world',
      ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('pre-acli-hello-world', $buffer);
  }

  /**
   * @group serial
   * @covers \Acquia\Cli\EventListener\ComposerScriptsListener::onConsoleTerminate
   */
  public function testPostScripts(): void {
    $json = [
      'scripts' => [
        'post-acli-hello-world' => [
          'echo "goodbye world"',
        ],
      ],
    ];
    file_put_contents(
      Path::join($this->projectDir, 'composer.json'),
      json_encode($json, JSON_THROW_ON_ERROR)
    );
    $this->mockRequest('getAccount');
    $this->setInput([
          'command' => 'hello-world',
      ]);
    $buffer = $this->runApp();
    self::assertStringContainsString('post-acli-hello-world', $buffer);
  }

  public function testNoScripts(): void {
    $json = [
      'scripts' => [
        'pre-acli-pull-code' => [
          'echo "goodbye world"',
        ],
      ],
    ];
    file_put_contents(
      Path::join($this->projectDir, 'composer.json'),
      json_encode($json, JSON_THROW_ON_ERROR)
    );
    $this->setInput([
      '--no-scripts' => TRUE,
      'command' => 'pull:code',
    ]);
    $buffer = $this->runApp();
    self::assertStringNotContainsString('pre-acli-pull-code', $buffer);
  }

}
