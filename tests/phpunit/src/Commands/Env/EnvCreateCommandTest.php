<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Env\EnvCreateCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;

/**
 * @property \Acquia\Cli\Command\Env\EnvCreateCommand $command
 */
class EnvCreateCommandTest extends CommandTestBase
{
    private static string $validLabel = 'New CDE';

    private function setupCdeTest(string $label, ?bool $apiSuccess = true): string
    {
        $applicationsResponse = $this->mockApplicationsRequest();
        $applicationResponse = $this->mockApplicationRequest();
        $this->mockEnvironmentsRequest($applicationsResponse);

        $response1 = self::getMockEnvironmentsResponse();
        $response2 = self::getMockEnvironmentsResponse();
        $cde = $response2->_embedded->items[0];
        $cde->label = $label;
        $response2->_embedded->items[3] = $cde;
        $this->clientProphecy->request(
            'get',
            "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments"
        )
            ->willReturn($response1->_embedded->items, $response2->_embedded->items)
            ->shouldBeCalled();

        $codeResponse = self::getMockResponseFromSpec("/applications/{applicationUuid}/code", 'get', '200');
        $this->clientProphecy->request(
            'get',
            "/applications/$applicationResponse->uuid/code"
        )
            ->willReturn($codeResponse->_embedded->items)
            ->shouldBeCalled();

        $databasesResponse = self::getMockResponseFromSpec("/applications/{applicationUuid}/databases", 'get', '200');
        $this->clientProphecy->request(
            'get',
            "/applications/$applicationResponse->uuid/databases"
        )
            ->willReturn($databasesResponse->_embedded->items)
            ->shouldBeCalled();

        $environmentsResponse = self::getMockResponseFromSpec(
            '/applications/{applicationUuid}/environments',
            'post',
            202
        );
        $this->clientProphecy->request('post', "/applications/$applicationResponse->uuid/environments", Argument::type('array'))
            ->willReturn($environmentsResponse->{'Adding environment'}->value)
            ->shouldBeCalled();

        $this->mockNotificationResponseFromObject($environmentsResponse->{'Adding environment'}->value, $apiSuccess);
        return $response2->_embedded->items[3]->domains[0];
    }

    private static function getBranch(): string
    {
        $codeResponse = self::getMockResponseFromSpec("/applications/{applicationUuid}/code", 'get', '200');
        return $codeResponse->_embedded->items[0]->name;
    }

    private static function getApplication(): string
    {
        $applicationsResponse = self::getMockResponseFromSpec(
            '/applications',
            'get',
            '200'
        );
        return $applicationsResponse->{'_embedded'}->items[0]->uuid;
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(EnvCreateCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestCreateCde(): array
    {
        $application = self::getApplication();
        $branch = self::getBranch();
        return [
            // No args, only interactive input.
            [[null, null], ['n', 0, 0]],
            // Branch as arg.
            [[$branch, null], ['n', 0]],
            // Branch and app id as args.
            [[$branch, $application], []],
        ];
    }

    /**
     * @dataProvider providerTestCreateCde
     * @group brokenProphecy
     */
    public function testCreateCde(mixed $args, mixed $input): void
    {
        $domain = $this->setupCdeTest(self::$validLabel);

        $this->executeCommand(
            [
                'applicationUuid' => $args[1],
                'branch' => $args[0],
                'label' => self::$validLabel,
            ],
            $input
        );

        $output = $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
        $this->assertStringContainsString("Your CDE URL: $domain", $output);
    }

    /**
     * @group brokenProphecy
     */
    public function testCreateCdeNonUniqueLabel(): void
    {
        $label = 'Dev';
        $this->setupCdeTest($label);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('An environment named Dev already exists.');
        $this->executeCommand(
            [
                'applicationUuid' => EnvCreateCommandTest::getApplication(),
                'branch' => EnvCreateCommandTest::getBranch(),
                'label' => $label,
            ]
        );
    }

    /**
     * @group brokenProphecy
     */
    public function testCreateCdeInvalidTag(): void
    {
        $this->setupCdeTest(self::$validLabel);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('There is no branch or tag with the name bogus on the remote VCS.');
        $this->executeCommand(
            [
                'applicationUuid' => EnvCreateCommandTest::getApplication(),
                'branch' => 'bogus',
                'label' => self::$validLabel,
            ]
        );
    }

    /**
     * @group brokenProphecy
     */
    public function testCreateCdeApiFailure(): void
    {
        $this->setupCdeTest(self::$validLabel, false);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Cloud API failed to create environment');

        $this->executeCommand(
            [
                'applicationUuid' => EnvCreateCommandTest::getApplication(),
                'branch' => EnvCreateCommandTest::getBranch(),
                'label' => self::$validLabel,
            ]
        );
    }
}
