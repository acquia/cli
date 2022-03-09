<?php

namespace Acquia\Cli\Tests\Commands\Email;

use Acquia\Cli\Command\Email\EmailInfoForSubscriptionCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
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
    // duplicating the request to ensure there is at least one domain with a successful, pending, and failed health code
    $get_domains_response_2 = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $total_domains_list = array_merge($get_domains_response->_embedded->items, $get_domains_response_2->_embedded->items);
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($total_domains_list);

    $total_domains_list[2]->domain_name = 'example3.com';
    $total_domains_list[2]->health->code = '200';

    $total_domains_list[3]->domain_name = 'example4.com';
    $total_domains_list[3]->health->code = '202';

    $applications_response = $this->mockApplicationsRequest();

    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $get_app_domains_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    // duplicating the request to ensure added domains are included in association list
    $get_app_domains_response_2 = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    $total_app_domains_list = array_merge($get_app_domains_response->_embedded->items, $get_app_domains_response_2->_embedded->items);
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains")->willReturn($total_app_domains_list);

    $total_app_domains_list[2]->domain_name = 'example3.com';
    $total_app_domains_list[2]->flags->associated = TRUE;

    $total_app_domains_list[3]->domain_name = 'example4.com';
    $total_app_domains_list[3]->flags->associated = FALSE;

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Application: ', $output);
    foreach($get_app_domains_response->_embedded->items as $app_domain) {
      $this->assertEquals(3, substr_count($output, $app_domain->domain_name));
    }

    $this->assertEquals(2, substr_count($output, 'Failed - 404'));
    $this->assertEquals(1, substr_count($output, 'Pending - 202'));
    $this->assertEquals(1, substr_count($output, 'Succeeded - 200'));

    $this->assertEquals(3, substr_count($output, 'true'));
    $this->assertEquals(1, substr_count($output, 'false'));
  }

  /**
   * Tests the 'email:info' command when the subscription has no applications.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function testEmailInfoForSubscriptionNoApps(): void {
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

    $this->mockApplicationsRequest();

    try {
      $this->executeCommand([], $inputs);
    }
    catch (AcquiaCliException $exception) {
      $this->assertStringContainsString("You do not have access", $exception->getMessage());
    }
  }

  /**
   * Tests the 'email:info' command when the subscription has over 100 applications.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testEmailInfoForSubscriptionWith101Apps(): void {
    $inputs = [
      // Please select a Cloud Platform subscription
      0,
      // Do you wish to continue?
      'no'
    ];
    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $applications_response = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;
    $applications_response->_embedded->items[1]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $app = $this->getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
    for ($i = 2; $i < 101; $i++) {
      $applications_response->_embedded->items[$i]= $app;
      $applications_response->_embedded->items[$i]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;
    }

    $this->clientProphecy->request('get', '/applications')->willReturn($applications_response->_embedded->items);

    foreach($applications_response->_embedded->items as $app) {
      $this->clientProphecy->request('get', "/applications/{$app->uuid}/email/domains")->willReturn([]);
    }

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(1, $this->getStatusCode());

  }

  /**
   * Tests the 'email:info' command when the subscription has no domains registred.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testEmailInfoForSubscriptionNoDomains(): void {
    $inputs = [
      // Please select a Cloud Platform subscription
      0,
    ];
    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn([]);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('No email domains', $output);
  }

  /**
   * Tests the 'email:info' command when the subscription's applications have no domains eligible for association.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testEmailInfoForSubscriptionNoAppDomains(): void {
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

    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains")->willReturn([]);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('No domains eligible', $output);
  }

}