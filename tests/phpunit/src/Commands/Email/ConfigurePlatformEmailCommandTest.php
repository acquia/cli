<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Email;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @property \Acquia\Cli\Command\Email\ConfigurePlatformEmailCommand $command
 */
class ConfigurePlatformEmailCommandTest extends CommandTestBase
{
    protected const JSON_TEST_OUTPUT = '[
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

    protected const ZONE_TEST_OUTPUT = "_acquiaplatform.example.com. 3600 IN TXT \"aGh54oW35sd5LMGhas1fWrnRrticnsdndf,43=\"\n" .
    "_amazonses.example.com. 3600 IN TXT \"AB/CD4Hef1+c0D7+wYS2xQ+EBr3HZiXRWDJHrjEWOhs=\"\n" .
    "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com. 3600 IN CNAME abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com.\n" .
    "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com. 3600 IN CNAME abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com.\n" .
    "abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com. 3600 IN CNAME abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com.\n" .
    "mail.example.com. 3600 IN MX 10 feedback-smtp.us-east-1.amazonses.com.\n" .
    "mail.example.com. 3600 IN TXT \"v=spf1 include:amazonses.com ~all\"";

    protected const YAML_TEST_OUTPUT = "-\n    type: TXT\n    name: _amazonses.example.com\n    value: AB/CD4Hef1+c0D7+wYS2xQ+EBr3HZiXRWDJHrjEWOhs=\n" .
    "-\n    type: TXT\n    name: _acquiaplatform.example.com\n    value: 'aGh54oW35sd5LMGhas1fWrnRrticnsdndf,43='\n" .
    "-\n    type: MX\n    name: mail.example.com\n    value: '10 feedback-smtp.us-east-1.amazonses.com'\n" .
    "-\n    type: TXT\n    name: mail.example.com\n    value: 'v=spf1 include:amazonses.com ~all'\n" .
    "-\n    type: CNAME\n    name: abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com\n    value: abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com\n" .
    "-\n    type: CNAME\n    name: abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com\n    value: abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com\n" .
    "-\n    type: CNAME\n    name: abcdefgh1ijkl2mnopq34rstuvwxyz._domainkey.example.com\n    value: abcdefgh1ijkl2mnopq34rstuvwxyz.dkim.amazonses.com\n";

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(ConfigurePlatformEmailCommand::class);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
        $this->command = $this->createCommand();
    }

    /**
     * @return array<mixed>
     */
    public function providerTestConfigurePlatformEmail(): array
    {

        return [
        [
        'test.com',
        'zone',
        self::ZONE_TEST_OUTPUT,
        [
        // What's the domain name you'd like to register?
        'test.com',
        // Select a Cloud Platform subscription.
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
        // Select a Cloud Platform subscription.
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
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '2',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        // Would you like to retry verification?
        'n',
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
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '0',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        // Would you like to refresh?
        'y',
        // Would you like to re-check domain verification?
        'n',
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
     * @return array<mixed>
     */
    public function providerTestConfigurePlatformEmailEnableEnv(): array
    {
        return [
        [
        'example.com',
        [
        // What's the domain name you'd like to register?
        'example.com',
        // Select a Cloud Platform subscription.
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
        // Enablement response code.
        '409',
        // Spec key for enablement response code.
        'Already enabled',
        // Expected text.
        ['already enabled', "You're all set to start using Platform Email!"],
        ],
        [
        'example.com',
        [
        // What's the domain name you'd like to register?
        'example.com',
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '0',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
        '0',
        ],
        // Status code.
        1,
        // Enablement response code.
        '403',
        // Spec key for enablement response code.
        'No permission',
        // Expected text.
        ['You do not have permission', 'Something went wrong'],
        ],
        ];
    }

    /**
     * @dataProvider providerTestConfigurePlatformEmail
     */
    public function testConfigurePlatformEmail(mixed $baseDomain, mixed $fileDumpFormat, mixed $fileDump, mixed $inputs, mixed $expectedExitCode, mixed $expectedText, mixed $responseCode): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items)
        ->shouldBeCalledTimes(1);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => $baseDomain,
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'test.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $domainsRegistrationResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
        $domainsRegistrationResponse->health->code = $responseCode;
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}")
        ->willReturn($domainsRegistrationResponse);

        $mockFileSystem->remove('dns-records.yaml')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.json')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.zone')->shouldBeCalled();

        $mockFileSystem->dumpFile('dns-records.' . $fileDumpFormat, $fileDump)->shouldBeCalled();

        if ($responseCode == '404') {
            $reverifyResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}/actions/verify', 'post', '200');
            $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}/actions/verify")
            ->willReturn($reverifyResponse);
        } elseif ($responseCode == '200') {
            $applicationsResponse = $this->mockApplicationsRequest();
            // We need the application to belong to the subscription.
            $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

            $associateResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
            $this->clientProphecy->request('post', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains/{$getDomainsResponse->_embedded->items[0]->uuid}/actions/associate")->willReturn($associateResponse);
            $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
            $enableResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
            $this->clientProphecy->request('post', "/environments/{$environmentsResponse->_embedded->items[0]->id}/email/actions/enable")->willReturn($enableResponse);
        }

        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();

        $this->assertEquals($expectedExitCode, $this->getStatusCode());
        foreach ($expectedText as $text) {
            $this->assertStringContainsString($text, $output);
        }
    }

    public function testConfigurePlatformEmailWithMultipleAppsAndEnvs(): void
    {
        $inputs = [
        // What's the domain name you'd like to register?
        'test.com',
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '0',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        // What are the applications you'd like to associate this domain with? You may enter multiple separated by a comma.
        '0,1',
        // What are the environments you'd like to enable email for? You may enter multiple separated by a comma. - Application 0.
        '0,1',
        // What are the environments you'd like to enable email for? You may enter multiple separated by a comma. - Application 1.
        '0',
        ];
        $localMachineHelper = $this->mockLocalMachineHelper();
        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items)
        ->shouldBeCalledTimes(1);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => 'test.com',
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'test.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $domainsRegistrationResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
        $domainsRegistrationResponse200 = $domainsRegistrationResponse;
        $domainsRegistrationResponse200->health->code = '200';
        // Passing in two responses will return the first response the first time
        // that the method is called, the second response the second time it is
        // called, etc.
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}")->willReturn($domainsRegistrationResponse, $domainsRegistrationResponse, $domainsRegistrationResponse200);

        $mockFileSystem->remove('dns-records.yaml')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.json')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.zone')->shouldBeCalled();
        $mockFileSystem->dumpFile('dns-records.zone', self::ZONE_TEST_OUTPUT)->shouldBeCalled();

        $applicationsResponse = $this->mockApplicationsRequest();
        // We need the application to belong to the subscription.
        $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;
        $applicationsResponse->_embedded->items[1]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

        $associateResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
        $this->clientProphecy->request('post', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains/{$getDomainsResponse->_embedded->items[0]->uuid}/actions/associate")->willReturn($associateResponse);
        $this->clientProphecy->request('post', "/applications/{$applicationsResponse->_embedded->items[1]->uuid}/email/domains/{$getDomainsResponse->_embedded->items[1]->uuid}/actions/associate")->willReturn($associateResponse);

        $environmentResponseApp1 = $this->getMockEnvironmentsResponse();
        $environmentResponseApp2 = $environmentResponseApp1;

        $this->clientProphecy->request('get', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/environments")->willReturn($environmentResponseApp1->_embedded->items);
        $this->clientProphecy->request('get', "/applications/{$applicationsResponse->_embedded->items[1]->uuid}/environments")->willReturn($environmentResponseApp2->_embedded->items);

        $enableResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
        $this->clientProphecy->request('post', "/environments/{$environmentResponseApp1->_embedded->items[0]->id}/email/actions/enable")->willReturn($enableResponse);
        $this->clientProphecy->request('post', "/environments/{$environmentResponseApp1->_embedded->items[1]->id}/email/actions/enable")->willReturn($enableResponse);

        $this->clientProphecy->request('post', "/environments/{$environmentResponseApp2->_embedded->items[0]->id}/email/actions/enable")->willReturn($enableResponse);

        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();

        $this->assertEquals(0, $this->getStatusCode());
        $this->assertStringContainsString("You're all set to start using Platform Email!", $output);
    }

    public function testConfigurePlatformEmailNoApps(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

        $baseDomain = 'test.com';
        $inputs = [
        // What's the domain name you'd like to register?
        $baseDomain,
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '0',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        ];

        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items)
        ->shouldBeCalledTimes(1);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => $baseDomain,
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'test.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $domainsRegistrationResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
        $domainsRegistrationResponse200 = $domainsRegistrationResponse;
        $domainsRegistrationResponse200->health->code = '200';

        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}")->willReturn($domainsRegistrationResponse200);

        $mockFileSystem->remove('dns-records.yaml')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.json')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.zone')->shouldBeCalled();

        $mockFileSystem->dumpFile('dns-records.zone', self::ZONE_TEST_OUTPUT)->shouldBeCalled();
        $applicationsResponse = $this->mockApplicationsRequest();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('You do not have access to any applications');
        $this->executeCommand([], $inputs);

        $output = $this->getDisplay();

        $this->assertStringNotContainsString("You're all set to start using Platform Email!", $output);
    }

    public function testConfigurePlatformEmailWithNoDomainMatch(): void
    {
        $baseDomain = 'test.com';
        $inputs = [
        // What's the domain name you'd like to register?
        $baseDomain,
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '0',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        ];

        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items)
        ->shouldBeCalledTimes(1);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => $baseDomain,
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'mismatch-test.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not find domain');
        $this->executeCommand([], $inputs);
    }

    public function testConfigurePlatformEmailWithErrorRetrievingDomainHealth(): void
    {
        $baseDomain = 'test.com';
        $inputs = [
        // What's the domain name you'd like to register?
        $baseDomain,
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '0',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        ];

        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items)
        ->shouldBeCalledTimes(1);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => $baseDomain,
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'test.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $domainsRegistrationResponse404 = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '404');

        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}")->willReturn($domainsRegistrationResponse404);
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Could not retrieve DNS records for this domain');
        $this->executeCommand([], $inputs);
    }

    /**
     * Tests the exported JSON file output when running email:configure, ensuring that slashes are encoded correctly.
     */
    public function testConfigurePlatformEmailJsonOutput(): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);

        $inputs = [
        // What's the domain name you'd like to register?
        'test.com',
        // Select a Cloud Platform subscription.
        '0',
        // Would you like your DNS records in BIND Zone File, JSON, or YAML format?
        '2',
        // Have you finished providing the DNS records to your DNS provider?
        'y',
        // What are the environments you'd like to enable email for? You may enter multiple separated by a comma.
        '0',
        ];
        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => 'test.com',
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'test.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $domainsRegistrationResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
        $domainsRegistrationResponse200 = $domainsRegistrationResponse;
        $domainsRegistrationResponse200->health->code = '200';

        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}")->willReturn($domainsRegistrationResponse200);
        $mockFileSystem->remove('dns-records.yaml')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.json')->shouldBeCalled();
        $mockFileSystem->remove('dns-records.zone')->shouldBeCalled();
        $mockFileSystem->dumpFile('dns-records.json', self::JSON_TEST_OUTPUT)->shouldBeCalled();
        $applicationsResponse = $this->mockApplicationsRequest();

        $appDomainsResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
        $appDomainsResponse->_embedded->items[0]->domain_name = 'test.com';
        $this->clientProphecy->request('get', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains")->willReturn($appDomainsResponse->_embedded->items);
        // We need the application to belong to the subscription.
        $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

        $associateResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '200');
        $this->clientProphecy->request('post', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains/{$getDomainsResponse->_embedded->items[0]->uuid}/actions/associate")->willReturn($associateResponse);

        $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
        $enableResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', '200');
        $this->clientProphecy->request('post', "/environments/{$environmentsResponse->_embedded->items[0]->id}/email/actions/enable")->willReturn($enableResponse);

        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();

        $this->assertEquals('0', $this->getStatusCode());
        $this->assertStringContainsString('all set', $output);
    }

    /**
     * @dataProvider providerTestConfigurePlatformEmailEnableEnv
     */
    public function testConfigurePlatformEmailWithAlreadyEnabledEnvs(mixed $baseDomain, mixed $inputs, mixed $expectedExitCode, mixed $responseCode, mixed $specKey, mixed $expectedText): void
    {
        $subscriptionsResponse = $this->getMockResponseFromSpec('/subscriptions', 'get', '200');
        $this->clientProphecy->request('get', '/subscriptions')
        ->willReturn($subscriptionsResponse->{'_embedded'}->items)
        ->shouldBeCalledTimes(1);

        $postDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'post', '200');
        $this->clientProphecy->request('post', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains", [
        'form_params' => [
        'domain' => $baseDomain,
        ],
        ])->willReturn($postDomainsResponse);

        $getDomainsResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains', 'get', '200');
        $getDomainsResponse->_embedded->items[0]->domain_name = 'example.com';
        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains")->willReturn($getDomainsResponse->_embedded->items);

        $domainsRegistrationResponse = $this->getMockResponseFromSpec('/subscriptions/{subscriptionUuid}/domains/{domainRegistrationUuid}', 'get', '200');
        $domainsRegistrationResponse200 = $domainsRegistrationResponse;
        $domainsRegistrationResponse200->health->code = '200';

        $this->clientProphecy->request('get', "/subscriptions/{$subscriptionsResponse->_embedded->items[0]->uuid}/domains/{$getDomainsResponse->_embedded->items[0]->uuid}")->willReturn($domainsRegistrationResponse200);

        $applicationsResponse = $this->mockApplicationsRequest();

        $appDomainsResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains', 'get', '200');
        $appDomainsResponse->_embedded->items[0]->domain_name = 'example.com';
        $this->clientProphecy->request('get', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains")->willReturn($appDomainsResponse->_embedded->items);
        // We need the application to belong to the subscription.
        $applicationsResponse->_embedded->items[0]->subscription->uuid = $subscriptionsResponse->_embedded->items[0]->uuid;

        $associateResponse = $this->getMockResponseFromSpec('/applications/{applicationUuid}/email/domains/{domainRegistrationUuid}/actions/associate', 'post', '409');

        $this->clientProphecy->request('post', "/applications/{$applicationsResponse->_embedded->items[0]->uuid}/email/domains/{$getDomainsResponse->_embedded->items[0]->uuid}/actions/associate")
        ->willThrow(new ApiErrorException($associateResponse->{'Already associated'}->value));

        $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
        $enableResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/email/actions/enable', 'post', $responseCode);
        $this->clientProphecy->request('post', "/environments/{$environmentsResponse->_embedded->items[0]->id}/email/actions/enable")
        ->willThrow(new ApiErrorException($enableResponse->{$specKey}->value));

        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();

        $this->assertEquals($expectedExitCode, $this->getStatusCode());
        foreach ($expectedText as $text) {
            $this->assertStringContainsString($text, $output);
        }
    }

    /**
     * @return \Symfony\Component\Filesystem\Filesystem|\Prophecy\Prophecy\ObjectProphecy
     */
    protected function mockGetFilesystem(ObjectProphecy|LocalMachineHelper $localMachineHelper): Filesystem|ObjectProphecy
    {
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()->willReturn($fileSystem->reveal())->shouldBeCalled();

        return $fileSystem;
    }
}
