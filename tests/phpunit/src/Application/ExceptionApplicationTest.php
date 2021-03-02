<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Kernel;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests exceptions rewritten by the Symfony Event Dispatcher.
 *
 * These must be tested using the ApplicationTestBase, since the Symfony
 * CommandTester does not fire Event Dispatchers.
 *
 * @package Acquia\Cli\Tests\Application
 */
class ExceptionApplicationTest extends TestBase {

  /**
   * Symfony kernel.
   *
   * @var \Acquia\Cli\Kernel
   */
  protected $kernel;

  public function setUp($output = NULL):void {
    parent::setUp($output);
    // If kernel is cached from a previous run, it doesn't get detected in code
    // coverage reports.
    $this->fs->remove('var/cache');
    $this->kernel = new Kernel('dev', 0);
    $this->kernel->boot();
    $this->kernel->getContainer()->set('datastore.cloud', $this->datastoreCloud);
    $this->kernel->getContainer()->set(ClientService::class, $this->clientServiceProphecy->reveal());
    $output = new BufferedOutput();
    $this->kernel->getContainer()->set(OutputInterface::class, $output);
  }

  protected function setInput($args = ['link']) {
    $input = new ArrayInput($args);
    $input->setInteractive(FALSE);
    $this->kernel->getContainer()->set(InputInterface::class, $input);
  }

  public function testInvalidApiCreds(): void {
    $this->setInput();
    $this->mockUnauthorizedRequest();
    $buffer = $this->runApp();
    // This is sensitive to the display width of the test environment, so that's fun.
    self::assertStringContainsString('Your Cloud Platform API credentials are invalid.', $buffer);
  }

  public function testApiError(): void {
    $this->setInput();
    $this->mockApiError();
    $buffer = $this->runApp();
    self::assertStringContainsString('Cloud Platform API returned an error:', $buffer);
  }

  public function testNoAvailableIdes(): void {
    $this->setInput();
    $this->mockNoAvailableIdes();
    $buffer = $this->runApp();
    self::assertStringContainsString('Delete an existing IDE', $buffer);
  }

  public function testMissingEnvironmentUuid(): void {
    $this->setInput(['log:tail']);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be an site alias.', $buffer);
  }

  public function testInvalidEnvironmentUuid(): void {
    $this->setInput(['log:tail', 'aoeuth']);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be an site alias.', $buffer);
  }

  public function testMissingApplicationUuid(): void {
    $this->setInput(['ide:open']);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be an application alias.', $buffer);
  }

  public function testInvalidApplicationUuid(): void {
    $this->setInput(['ide:open', 'aoeuthao']);
    $buffer = $this->runApp();
    self::assertStringContainsString('can also be an application alias.', $buffer);
  }

  /**
   * @return string
   */
  protected function runApp(): string {
    $input = $this->kernel->getContainer()->get(InputInterface::class);
    $output = $this->kernel->getContainer()->get(OutputInterface::class);
    $application = $this->kernel->getContainer()->get(Application::class);
    $application->setAutoExit(FALSE);
    $application->run($input, $output);
    return $output->fetch();
  }

}
