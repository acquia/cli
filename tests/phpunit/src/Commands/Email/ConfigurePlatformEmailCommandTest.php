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
   * Tests the 'clear-caches' command.
   *
   * @throws \Exception
   */
  public function testConfigurePlatformEmail(): void {
    $base_domain = 'https://www.test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain
    ];

    // Request for subscriptions.
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
    $this->assertStringContainsString('Acquia CLI caches were cleared', $output);
  }

}
