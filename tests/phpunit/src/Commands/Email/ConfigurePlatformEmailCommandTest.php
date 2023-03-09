<?php

namespace Acquia\Cli\Tests\Commands\Email;

use Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ConfigurePlatformEmailCommandTest.
 *
 * @property \Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class ConfigurePlatformEmailCommandTest extends CommandTestBase {

  const JSON_TEST_OUTPUT = '[
    {
        "type": "TXT",
        "name": "_amazonses.example.com",
        "value": "AB/CD4Hef1+c0D7+wYS2xQ+EBr3HZiXRWDJHrjEWOhs="
    },
    {
        "type": "TXT",
        "name": "_acquiaplatform.example.com",
        "value": "aGh54oW35sd5LMGhas1fWrnRrticnsdndf,43="
    },
    {
        "type": "MX",
        "name": "mail.example.com",
        "value": "10 feedback-smtp.us-east-1.amazonses.com"
    },
    {
        "type": "TXT",
        "name": "mail.example.com",
        "value": "v=spf1 include:amazonses.com ~all"
    },
    {
        "type": "CNAME",
        "name": "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com",
        "value": "abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com"
    },
    {
        "type": "CNAME",
        "name": "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com",
        "value": "abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com"
    },
    {
        "type": "CNAME",
        "name": "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com",
        "value": "abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com"
    }
]';

  const ZONE_TEST_OUTPUT = "_acquiaplatform.example.com. 3600 IN TXT \"aGh54oW35sd5LMGhas1fWrnRrticnsdndf,43=\"\n" .
  "_amazonses.example.com. 3600 IN TXT \"AB/CD4Hef1+c0D7+wYS2xQ+EBr3HZiXRWDJHrjEWOhs=\"\n" .
  "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com. 3600 IN CNAME abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com.\n" .
  "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com. 3600 IN CNAME abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com.\n" .
  "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com. 3600 IN CNAME abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com.\n" .
  "mail.example.com. 3600 IN MX 10 feedback-smtp.us-east-1.amazonses.com.\n" .
  "mail.example.com. 3600 IN TXT \"v=spf1 include:amazonses.com ~all\"";

  const YAML_TEST_OUTPUT = "-\n    type: TXT\n    name: _amazonses.example.com\n    value: AB/CD4Hef1+c0D7+wYS2xQ+EBr3HZiXRWDJHrjEWOhs=\n" .
  "-\n    type: TXT\n    name: _acquiaplatform.example.com\n    value: 'aGh54oW35sd5LMGhas1fWrnRrticnsdndf,43='\n" .
  "-\n    type: MX\n    name: mail.example.com\n    value: '10 feedback-smtp.us-east-1.amazonses.com'\n" .
  "-\n    type: TXT\n    name: mail.example.com\n    value: 'v=spf1 include:amazonses.com ~all'\n" .
  "-\n    type: CNAME\n    name: abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com\n    value: abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com\n" .
  "-\n    type: CNAME\n    name: abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com\n    value: abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com\n" .
  "-\n    type: CNAME\n    name: abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com\n    value: abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com\n";

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ConfigurePlatformEmailCommand::class);
  }

  /**
   * @throws \JsonException
   */
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->setupFsFixture();
    $this->command = $this->createCommand();
  }

  /**
   * @return array
   */
  public function providerTestConfigurePlatformEmail(): array {

    return [
      [
        'test.com',
        'zone',
        self::ZONE_TEST_OUTPUT,
        [
          // What's the domain name you'd like to register?
          'test.com',
          // Select a Cloud Platform subscription
          '0',
          // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
          '0',
          // Have you finished providing the DNS records to your DNS provider?
          'y',
          // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
          '0',
        ],
        // Status code.
        0,
        // Expected text.
        ["You're all set to start using Platform Email!"],
        // Domain registration responses.
        "200",
      ],
      [
        'test.com',
        'yaml',
        self::YAML_TEST_OUTPUT,
        [
          // What's the domain name you'd like to register?
          'test.com',
          // Select a Cloud Platform subscription
          '0',
          // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
          '1',
          // Have you finished providing the DNS records to your DNS provider?
          'n',
        ],
        // Status code.
        1,
        // Expected text.
        ["Make sure to give these records to your DNS provider"],
        // Domain registration responses.
        "404",
      ],
      [
        'test.com',
        'json',
        self::JSON_TEST_OUTPUT,
        [
          // What's the domain name you'd like to register?
          'test.com',
          // Select a Cloud Platform subscription
          '0',
          // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
          '2',
          // Have you finished providing the DNS records to your DNS provider?
          'y',
          // Would you like to retry verification?
          'n'
        ],
        // Status code.
        1,
        // Expected text.
        ["Verification pending...", "Check your DNS records with your DNS provider"],
        // Domain registration responses.
        "202",
      ],
      [
        'test.com',
        'zone',
        self::ZONE_TEST_OUTPUT,
        [
          // What's the domain name you'd like to register?
          'test.com',
          // Select a Cloud Platform subscription
          '0',
          // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
          '0',
          // Have you finished providing the DNS records to your DNS provider?
          'y',
          // Would you like to refresh?
          'y',
          //  Would you like to re-check domain verification?
          'n'
        ],
        // Status code.
        1,
        // Expected text.
        ["Refreshing...", "Check your DNS records with your DNS provider"],
        // Domain registration responses.
        "404",
      ],
    ];
  }

  /**
   * @return array
   */
  public function providerTestConfigurePlatformEmailEnableEnv(): array {
    return [
      [
        'example.com',
        [
          // What's the domain name you'd like to register?
          'example.com',
          // Select a Cloud Platform subscription
          '0',
          // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
          '0',
          // Have you finished providing the DNS records to your DNS provider?
          'y',
          // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
          '0'
        ],
        // Status code.
        0,
        // Enablement response code.
        '409',
        // Spec key for enablement response code.
        'Already enabled',
        // Expected text.
        ['already enabled', "You're all set to start using Platform Email!"]
      ],
      [
        'example.com',
        [
          // What's the domain name you'd like to register?
          'example.com',
          // Select a Cloud Platform subscription
          '0',
          // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
          '0',
          // Have you finished providing the DNS records to your DNS provider?
          'y',
          // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
          '0'
        ],
        // Status code.
        1,
        // Enablement response code.
        '403',
        // Spec key for enablement response code.
        'No permission',
        // Expected text.
        ['You do not have permission', 'Something went wrong']
      ],
    ];
  }

  /**
   * Tests the 'email:configure' command.
   *
   * @dataProvider providerTestConfigurePlatformEmail
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testConfigurePlatformEmail($base_domain, $file_dump_format, $file_dump, $inputs, $expected_exit_code, $expected_text, $response_code): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);

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
    $domains_registration_response->health->code = $response_code;
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")
      ->willReturn($domains_registration_response);

    $mock_file_system->remove('dns-records.yaml')->shouldBeCalled();
    $mock_file_system->remove('dns-records.json')->shouldBeCalled();
    $mock_file_system->remove('dns-records.zone')->shouldBeCalled();

    $mock_file_system->dumpFile('dns-records.' . $file_dump_format, $file_dump)->shouldBeCalled();

    if ($response_code == '404') {
      $reverify_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}/actions/verify', 'post', '200');
      $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}/actions/verify")
        ->willReturn($reverify_response);
    }
    else if ($response_code == '200') {
      $applications_response = $this->mockApplicationsRequest();
      // We need the application to belong to the subscription.
      $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

      $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
      $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{$get_domains_response->_embedded->items[0]->uuid}/actions/associate")->willReturn($associate_response);
      $environments_response = $this->mockEnvironmentsRequest($applications_response);
      $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
      $this->clientProphecy->request('post', "/environments/{$environments_response->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);
    }

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->prophet->checkPredictions();
    $this->assertEquals($expected_exit_code, $this->getStatusCode());
    foreach ($expected_text as $text) {
      $this->assertStringContainsString($text, $output);
    }

  }

  public function testConfigurePlatformEmailWithMultipleAppsAndEnvs(): void {
    $inputs = [
      // What's the domain name you'd like to register?
      'test.com',
      // Select a Cloud Platform subscription
      '0',
      // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
      // What are the applications you'd like to associate this domain with? You may enter multiple separated by a comma.
      '0,1',
      // What are the environments you'd like to enable email for? You may enter multiple separated by a comma. - Application 0
      '0,1',
      // What are the environments you'd like to enable email for? You may enter multiple separated by a comma. - Application 1
      '0',
    ];
    $local_machine_helper = $this->mockLocalMachineHelper();
    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);

    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items)
      ->shouldBeCalledTimes(1);

    $post_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
    $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains", [
      'form_params' => [
        'domain' => 'test.com',
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

    $mock_file_system->remove('dns-records.yaml')->shouldBeCalled();
    $mock_file_system->remove('dns-records.json')->shouldBeCalled();
    $mock_file_system->remove('dns-records.zone')->shouldBeCalled();
    $mock_file_system->dumpFile('dns-records.zone', self::ZONE_TEST_OUTPUT)->shouldBeCalled();

    $applications_response = $this->mockApplicationsRequest();
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;
    $applications_response->_embedded->items[1]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{$get_domains_response->_embedded->items[0]->uuid}/actions/associate")->willReturn($associate_response);
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[1]->uuid}/email/domains/{$get_domains_response->_embedded->items[1]->uuid}/actions/associate")->willReturn($associate_response);

    $environment_response_app_1 = $this->getMockEnvironmentsResponse();
    $environment_response_app_2 = $environment_response_app_1;

    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/environments")->willReturn($environment_response_app_1->_embedded->items);
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[1]->uuid}/environments")->willReturn($environment_response_app_2->_embedded->items);

    $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
    $this->clientProphecy->request('post', "/environments/{$environment_response_app_1->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);
    $this->clientProphecy->request('post', "/environments/{$environment_response_app_1->_embedded->items[1]->id}/email/actions/enable")->willReturn($enable_response);

    $this->clientProphecy->request('post', "/environments/{$environment_response_app_2->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->prophet->checkPredictions();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString("You're all set to start using Platform Email!", $output);

  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
   * @throws \Exception
   */
  public function testConfigurePlatformEmailNoApps(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);

    $base_domain = 'test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Select a Cloud Platform subscription
      '0',
      // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
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

    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response_200);

    $mock_file_system->remove('dns-records.yaml')->shouldBeCalled();
    $mock_file_system->remove('dns-records.json')->shouldBeCalled();
    $mock_file_system->remove('dns-records.zone')->shouldBeCalled();

    $mock_file_system->dumpFile('dns-records.zone', self::ZONE_TEST_OUTPUT)->shouldBeCalled();
    $applications_response = $this->mockApplicationsRequest();

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('You do not have access to any applications');
    $this->executeCommand([], $inputs);

    $output = $this->getDisplay();
    $this->prophet->checkPredictions();
    $this->assertStringNotContainsString("You're all set to start using Platform Email!", $output);
  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
   */
  public function testConfigurePlatformEmailWithNoDomainMatch(): void {
    $base_domain = 'test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Select a Cloud Platform subscription
      '0',
      // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
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
    $get_domains_response->_embedded->items[0]->domain_name = 'mismatch-test.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Could not find domain');
    $this->executeCommand([], $inputs);

    $this->prophet->checkPredictions();

  }

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \JsonException
   * @throws \Exception
   */
  public function testConfigurePlatformEmailWithErrorRetrievingDomainHealth(): void {
    $base_domain = 'test.com';
    $inputs = [
      // What's the domain name you'd like to register?
      $base_domain,
      // Select a Cloud Platform subscription
      '0',
      // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
      '0',
      // Have you finished providing the DNS records to your DNS provider?
      'y',
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

    $domains_registration_response_404 = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '404');

    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response_404);
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Could not retrieve DNS records for this domain');
    $this->executeCommand([], $inputs);

    $this->prophet->checkPredictions();

  }

  /**
   * Tests the exported JSON file output when running email:configure, ensuring that slashes are encoded correctly.
   */
  public function testConfigurePlatformEmailJsonOutput(): void {
    $local_machine_helper = $this->mockLocalMachineHelper();
    $mock_file_system = $this->mockGetFilesystem($local_machine_helper);

    $inputs = [
        // What's the domain name you'd like to register?
        'test.com',
        // Select a Cloud Platform subscription
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '2',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
        '0',
      ];
    $subscriptions_response = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
    $this->clientProphecy->request('get', '/subscriptions')
      ->willReturn($subscriptions_response->{'_embedded'}->items);

    $post_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
    $this->clientProphecy->request('post', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains", [
      'form_params' => [
        'domain' => 'test.com',
      ],
    ])->willReturn($post_domains_response);

    $get_domains_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
    $get_domains_response->_embedded->items[0]->domain_name = 'test.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $domains_registration_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
    $domains_registration_response_200 = $domains_registration_response;
    $domains_registration_response_200->health->code = '200';

    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response_200);
    $mock_file_system->remove('dns-records.yaml')->shouldBeCalled();
    $mock_file_system->remove('dns-records.json')->shouldBeCalled();
    $mock_file_system->remove('dns-records.zone')->shouldBeCalled();
    $mock_file_system->dumpFile('dns-records.json', self::JSON_TEST_OUTPUT)->shouldBeCalled();
    $applications_response = $this->mockApplicationsRequest();

    $app_domains_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    $app_domains_response->_embedded->items[0]->domain_name = 'test.com';
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains")->willReturn($app_domains_response->_embedded->items);
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{$get_domains_response->_embedded->items[0]->uuid}/actions/associate")->willReturn($associate_response);

    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
    $this->clientProphecy->request('post', "/environments/{$environments_response->_embedded->items[0]->id}/email/actions/enable")->willReturn($enable_response);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->prophet->checkPredictions();
    $this->assertEquals('0', $this->getStatusCode());
    $this->assertStringContainsString('all set', $output);
  }

  /**
   * Tests the 'email:configure' command when enabling email on an environment throws an API error.
   *
   * @dataProvider providerTestConfigurePlatformEmailEnableEnv
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testConfigurePlatformEmailWithAlreadyEnabledEnvs($base_domain, $inputs, $expected_exit_code, $response_code, $spec_key, $expected_text): void {
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
    $get_domains_response->_embedded->items[0]->domain_name = 'example.com';
    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains")->willReturn($get_domains_response->_embedded->items);

    $domains_registration_response = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
    $domains_registration_response_200 = $domains_registration_response;
    $domains_registration_response_200->health->code = '200';

    $this->clientProphecy->request('get', "/subscriptions/{$subscriptions_response->_embedded->items[0]->uuid}/domains/{$get_domains_response->_embedded->items[0]->uuid}")->willReturn($domains_registration_response_200);

    $applications_response = $this->mockApplicationsRequest();

    $app_domains_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
    $app_domains_response->_embedded->items[0]->domain_name = 'example.com';
    $this->clientProphecy->request('get', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains")->willReturn($app_domains_response->_embedded->items);
    // We need the application to belong to the subscription.
    $applications_response->_embedded->items[0]->subscription->uuid = $subscriptions_response->_embedded->items[0]->uuid;

    $associate_response = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '409');

    $this->clientProphecy->request('post', "/applications/{$applications_response->_embedded->items[0]->uuid}/email/domains/{$get_domains_response->_embedded->items[0]->uuid}/actions/associate")
      ->willThrow(new ApiErrorException($associate_response->{'Already associated'}->value));

    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $enable_response = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', $response_code);
    $this->clientProphecy->request('post', "/environments/{$environments_response->_embedded->items[0]->id}/email/actions/enable")
      ->willThrow(new ApiErrorException($enable_response->{$spec_key}->value));

    $this->executeCommand([], $inputs);
    $output = $this->getDisplay();
    $this->prophet->checkPredictions();
    $this->assertEquals($expected_exit_code, $this->getStatusCode());
    foreach ($expected_text as $text) {
      $this->assertStringContainsString($text, $output);
    }

  }

  /**
   * @return \Symfony\Component\Filesystem\Filesystem|\Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockGetFilesystem(ObjectProphecy|LocalMachineHelper $local_machine_helper): Filesystem|ObjectProphecy {
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();

    return $file_system;
  }

}
