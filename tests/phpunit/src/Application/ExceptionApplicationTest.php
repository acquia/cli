<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Tests\ApplicationTestBase;

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
    parent::setUp($output);
    // We need to call any command accessing the API, doesn't matter which.
    $this->application->addCommands([new LinkCommand()]);
  }

  public function testInvalidApiCreds(): void {
    $cloud_client = $this->getMockClient();
    // Simulate the response from OAuth server due to invalid credentials.
    $cloud_client->request('get', '/applications')
      ->willReturn('invalid_client')
      ->shouldBeCalled();
    $this->app->run(['link'], ['interactive' => FALSE]);
    $output = $this->app->getDisplay();
    $this->assertStringContainsString("Your Cloud API credentials are invalid. Run acli auth:login to reset them.", $output);
  }
}