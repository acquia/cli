<?php

namespace Acquia\Cli\Tests\Commands\Email;

use Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class ClearCacheCommandTest.
 *
 * @property \Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class ConfigurePlatformEmailCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ConfigurePlatformEmailCommand::class);
  }

  /**
   * Tests the 'email:configure' command.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testConfigurePlatformEmail(): void {
    $base_domain = 'https://www.test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Please select a Cloud Platform subscription
      '0',
      //Would you like your output in JSON or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
      // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
      '0',
    ];

    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $post_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
    $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ])->willReturn($post_domains_response);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $get_domains_response->_embedded->items[0]->domain_name = 'test.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $domains_registration_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
    $domains_registration_response_200 = $domains_registration_response;
    $domains_registration_response_200->health->code = '200';
    // Passing in two responses will return the first response the first time
    // that the method is called, the second response the second time it is
    // called, etc.
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response, $domains_registration_response, $domains_registration_response_200);

    $applications_response = $this->mockApplicationsRequest();
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{{$get_domains_response->_embedded->items[0]->uuid}}/actions/associate")->willReturn($associate_response);
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
    $this->clientProphecy->request('post', "/environments/{$environments_response->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("You're all set to start using Platform Email!", $output);
  }

  public function testConfigurePlatformEmailWithoutProvidingRecords(): void {
    $base_domain = 'https://www.test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Please select a Cloud Platform subscription
      '0',
      //Would you like your output in JSON or YAML format?
      '1',
      // Have you finished providing the DNS records to your DNS provider?
      'n',
    ];

    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $post_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
    $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ])->willReturn($post_domains_response);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $get_domains_response->_embedded->items[0]->domain_name = 'test.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);
    $domains_registration_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(1, $this->getStatusCode());
    $this->assertStringContainsString("Make sure to give these records to your DNS provider", $output);
  }

  public function testConfigurePlatformEmailWithMultipleAppsAndEnvs(): void {
    $base_domain = 'https://www.test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Please select a Cloud Platform subscription
      '0',
      //Would you like your output in JSON or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
      // What are the applications you'd like to associate this domain with? You may enter multiple separated by a comma.
      '0,1',
      // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
      '0,1',
      // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
      '0',
    ];

    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $post_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
    $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ])->willReturn($post_domains_response);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $get_domains_response->_embedded->items[0]->domain_name = 'test.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $domains_registration_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
    $domains_registration_response_200 = $domains_registration_response;
    $domains_registration_response_200->health->code = '200';
    // Passing in two responses will return the first response the first time
    // that the method is called, the second response the second time it is
    // called, etc.
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response, $domains_registration_response, $domains_registration_response_200);

    $applications_response = $this->mockApplicationsRequest();
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;
    $applications_response->_embedded->items[1]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{{$get_domains_response->_embedded->items[0]->uuid}}/actions/associate")->willReturn($associate_response);
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[1]->uuid}/email/domains/{{$get_domains_response->_embedded->items[1]->uuid}}/actions/associate")->willReturn($associate_response);

    $environment_response_app_1 = $this->getMockEnvironmentsResponse();
    $environment_response_app_2 = $environment_response_app_1;

    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/environments")->willReturn($environment_response_app_1->_embedded->items);
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[1]->uuid}/environments")->willReturn($environment_response_app_2->_embedded->items);

    $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
    $this->clientProphecy->request('post', "/environments/{$environment_response_app_1->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);
    $this->clientProphecy->request('post', "/environments/{$environment_response_app_1->_embedded->items[1]->id}/email/actions/enable")->willReturn($enable_response);

    $this->clientProphecy->request('post', "/environments/{$environment_response_app_2->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("You're all set to start using Platform Email!", $output);

  }

  public function testConfigurePlatformEmailWithReverifyDomain(): void {
    $base_domain = 'https://www.test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Please select a Cloud Platform subscription
      '0',
      //Would you like your output in JSON or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
      // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
      '0',
    ];

    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $post_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
    $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains", [
      'form_params' => [
        'domain' => $base_domain,
      ],
    ])->willReturn($post_domains_response);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $get_domains_response->_embedded->items[0]->domain_name = 'test.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $domains_registration_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
    $domains_registration_response_404 = $domains_registration_response;
    $domains_registration_response_404->health->code = '404';
    // Passing in two responses will return the first response the first time
    // that the method is called, the second response the second time it is
    // called, etc.
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response, $domains_registration_response, $domains_registration_response_200);

    $applications_response = $this->mockApplicationsRequest();
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{{$get_domains_response->_embedded->items[0]->uuid}}/actions/associate")->willReturn($associate_response);
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
    $this->clientProphecy->request('post', "/environments/{$environments_response->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("You're all set to start using Platform Email!", $output);
  }

}
