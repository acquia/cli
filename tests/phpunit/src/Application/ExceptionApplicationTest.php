<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Helpers\ClientService;
use Acquia\Cli\Kernel;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Connector\Client;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
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
    // If kernel is cached from a previous run, it doesn't get detected in code
    // coverage reports.
    shell_exec('rm -rf var/cache');
    parent::setUp($output);
  }

  public function testInvalidApiCreds(): void {
    $kernel = new Kernel('dev', 0);
    $kernel->boot();
    // Simulate the response from OAuth server due to invalid credentials.
   // $this->clientProphecy->request('get', '/applications')
     // ->willThrow(new IdentityProviderException('invalid_client', 0, ['error' => 'invalid_client', 'error_description' => 'The client credentials are invalid']))
      //->shouldBeCalled();
    $container = $kernel->getContainer();
    //$container->set(Client::class, $this->clientProphecy->reveal());
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
