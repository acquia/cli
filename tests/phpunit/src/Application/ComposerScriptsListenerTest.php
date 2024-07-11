<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Command\HelloWorldCommand;
use Acquia\Cli\EventListener\ComposerScriptsListener;
use Acquia\Cli\Tests\ApplicationTestBase;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Filesystem\Path;

/**
 * Tests Composer hooks handled by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 */
class ComposerScriptsListenerTest extends ApplicationTestBase
{
    /**
     * @group serial
     */
    public function testPreScripts(): void
    {
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
     */
    public function testPostScripts(): void
    {
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

    public function testNoScripts(): void
    {
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
            '--no-scripts' => true,
            'command' => 'pull:code',
        ]);
        $buffer = $this->runApp();
        self::assertStringNotContainsString('pre-acli-pull-code', $buffer);
    }

    // Hack to ensure listener methods are recognized as used.
    // If we were unit testing properly, we'd make meaningful assertions here instead of the integration tests above.
    public function testApi(): void
    {
        $listener = new ComposerScriptsListener();
        $listener->onConsoleCommand(new ConsoleCommandEvent($this->injectCommand(HelloWorldCommand::class), $this->input, $this->output));
        $listener->onConsoleTerminate(new ConsoleTerminateEvent($this->injectCommand(HelloWorldCommand::class), $this->input, $this->output, 0));
        self::assertTrue(true);
    }
}
