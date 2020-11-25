<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @package Acquia\Cli\Tests\Application
 */
class KernelTest extends TestCase {

  public function setUp():void {
    // If kernel is cached from a previous run, it doesn't get detected in code
    // coverage reports.
    shell_exec('rm -rf var/cache');
  }

  public function testRun(): void {
    $kernel = new Kernel('dev', 0);
    $kernel->boot();
    $container = $kernel->getContainer();
    $application = $container->get(Application::class);
    $application->setAutoExit(FALSE);
    $input = new ArgvInput(['list']);
    $output = new BufferedOutput();
    $application->run($input, $output);
    $buffer = $output->fetch();
    $this->assertStringContainsString('Available commands:', $buffer);
  }

}
