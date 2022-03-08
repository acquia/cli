<?php

namespace Acquia\Cli\Tests\Commands\Email;

use Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand;
use Acquia\Cli\Command\Email\EmailInfoForSubscriptionCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Console\Command\Command;

/**
 * Class ConfigurePlatformEmailCommandTest.
 *
 * @property \Acquia\Cli\Command\Email\EmailInfoForSubscriptionCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class EmailInfoForSubscriptionCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(EmailInfoForSubscriptionCommand::class);
  }

  /**
   * Tests the 'email:info' command.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testEmailInfoForSubscription(): void {
    $inputs = [
      // Please select a Cloud Platform subscription
      0,
    ];
    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $applications_response = $this->mockApplicationsRequest();
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;
    $applications_response->_embedded->items[1]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;
    $get_app_domains_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains")->willReturn($get_app_domains_response->_embedded->items);
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[1]->uuid}/email/domains")->willReturn($get_app_domains_response->_embedded->items);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Application: ', $output);
    $this->assertEquals(4, substr_count($output, $get_app_domains_response->_embedded->items[0]->domain_name));
  }

}