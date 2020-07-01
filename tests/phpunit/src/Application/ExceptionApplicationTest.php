<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Kernel;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests exceptions rewritten by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 *
 * @package Acquia\Cli\Tests\Application
 */
class ExceptionApplicationTest extends TestBase {

  public function setUp($output = NULL):void {
    parent::setUp($output);
    // If kernel is cached from a previous run, it doesn't get detected in code
    // coverage reports.
    $this->fs->remove('var/cache');
  }

  public function testInvalidApiCreds(): void {
    $kernel = new Kernel('dev', 0);
    $kernel->boot();
    $container = $kernel->getContainer();
    $container->set('datastore.cloud', $this->cloudDatastore);
    $application = $container->get(Application::class);
    $application->setAutoExit(FALSE);
    $input = new ArrayInput(['link']);
    $input->setInteractive(FALSE);
    $output = new BufferedOutput();
    $application->run($input, $output);
    $buffer = $output->fetch();
    $this->assertStringContainsString('Your Cloud API credentials are invalid. Run acli auth:login to reset them.', $buffer);  }

}
