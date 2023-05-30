<?php

namespace Acquia\Cli\Tests\Commands\Email;

use Acquia\Cli\Command\Email\EmailInfoForSubscriptionCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Email\EmailInfoForSubscriptionCommand $command
 */
class EmailInfoForSubscriptionCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(EmailInfoForSubscriptionCommand::class);
  }

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
    $this->command = $this->createCommand();
  }

  public function testEmailInfoForSubscription(): void {
    $inputs = [
      // Select a Cloud Platform subscription
      0,
    ];
    $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptionsResponse->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    // duplicating the request to ensure there is at least one domain with a successful, pending, and failed health code
    $getDomainsResponse2 = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $totalDomainsList = array_merge($getDomainsResponse->_embedded->items, $getDomainsResponse2->_embedded->items);
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($totalDomainsList);

    $totalDomainsList[2]->domain_name = 'example3.com';
    $totalDomainsList[2]->health->code = '200';

    $totalDomainsList[3]->domain_name = 'example4.com';
    $totalDomainsList[3]->health->code = '202';

    $applicationsResponse = $this->mockApplicationsRequest();

    $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

    $getAppDomainsResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    // duplicating the request to ensure added domains are included in association list
    $getAppDomainsResponse2 = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    $totalAppDomainsList = array_merge($getAppDomainsResponse->_embedded->items, $getAppDomainsResponse2->_embedded->items);
    $this->clientProphecy->request('get', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains")->willReturn($totalAppDomainsList);

    $totalAppDomainsList[2]->domain_name = 'example3.com';
    $totalAppDomainsList[2]->flags->associated = TRUE;

    $totalAppDomainsList[3]->domain_name = 'example4.com';
    $totalAppDomainsList[3]->flags->associated = FALSE;

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Application: ', $output);
    foreach ($getAppDomainsResponse->_embedded->items as $appDomain) {
      $this->assertEquals(3, substr_count($output, $appDomain->domain_name));
    }

    $this->assertEquals(2, substr_count($output, 'Failed - 404'));
    $this->assertEquals(1, substr_count($output, 'Pending - 202'));
    $this->assertEquals(1, substr_count($output, 'Succeeded - 200'));

    $this->assertEquals(3, substr_count($output, 'true'));
    $this->assertEquals(1, substr_count($output, 'false'));
  }

  public function testEmailInfoForSubscriptionNoApps(): void {
    $inputs = [
      // Select a Cloud Platform subscription
      0,
    ];
    $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptionsResponse->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

    $this->mockApplicationsRequest();

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('You do not have access');
    $this->executeCommand([], $inputs);
  }

  public function testEmailInfoForSubscriptionWith101Apps(): void {
    $inputs = [
      // Select a Cloud Platform subscription
      0,
      // Do you wish to continue?
      'no',
    ];
    $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptionsResponse->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

    $applicationsResponse = $this->getMockResponseFromSpec('/applications', 'get', '200');
    $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;
    $applicationsResponse->_embedded->items[1]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

    $app = $this->getMockResponseFromSpec('/applications/{applicationUuid}', 'get', '200');
    for ($i = 2; $i < 101; $i++) {
      $applicationsResponse->_embedded->items[$i] = $app;
      $applicationsResponse->_embedded->items[$i]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;
    }

    $this->clientProphecy->request('get', '/applications')->willReturn($applicationsResponse->_embedded->items);

    foreach ($applicationsResponse->_embedded->items as $app) {
      $this->clientProphecy->request('get', "/applications/{$app->uuid}/email/domains")->willReturn([]);
    }

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(1, $this->getStatusCode());

  }

  public function testEmailInfoForSubscriptionNoDomains(): void {
    $inputs = [
      // Select a Cloud Platform subscription
      0,
    ];
    $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptionsResponse->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn([]);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('No email domains', $output);
  }

  public function testEmailInfoForSubscriptionNoAppDomains(): void {
    $inputs = [
      // Select a Cloud Platform subscription
      0,
    ];
    $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptionsResponse->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

    $applicationsResponse = $this->mockApplicationsRequest();

    $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

    $this->clientProphecy->request('get', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains")->willReturn([]);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertStringContainsString('No domains eligible', $output);
  }

}
