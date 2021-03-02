<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Application;
use Acquia\Cli\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
    $input = new ArrayInput(['list']);
    $input->setInteractive(FALSE);
    $container->set(InputInterface::class, $input);
    $output = new BufferedOutput();
    $container->set(OutputInterface::class, $output);
    $application = $container->get(Application::class);
    $application->setAutoExit(FALSE);
    $application->run($input, $output);
    $buffer = $output->fetch();
    $this->assertStringContainsString('Available commands:', $buffer);
  }

}
