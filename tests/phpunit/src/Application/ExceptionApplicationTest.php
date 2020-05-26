<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Tests\ApplicationTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

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
    // Simulate the response from OAuth server due to invalid credentials.
    $this->clientProphecy->request('get', '/applications')
      ->willThrow(new IdentityProviderException('invalid_client', 0, ['error' => 'invalid_client', 'error_description' => "The client credentials are invalid"]))
      ->shouldBeCalled();
    $this->applicationTester->run(['link'], ['interactive' => FALSE]);
    $output = $this->applicationTester->getDisplay();
    $this->assertStringContainsString("Your Cloud API credentials are invalid. Run acli auth:login to reset them.", $output);
  }
}