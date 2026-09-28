<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\CloudApi\OktaConfig;
use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Connector;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;

/**
 * Routing tests that stub OktaConfig rather than exporting its variables.
 *
 * @property AuthLoginCommand $command
 */
class AuthLoginRoutingTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->useTty()->willReturn(false);
        $localMachineHelper->isBrowserAvailable()->willReturn(false);

        $oktaConfig = $this->prophet->prophesize(OktaConfig::class);
        $oktaConfig->isConfigured()->willReturn(true);

        return new AuthLoginCommand(
            $localMachineHelper->reveal(),
            $this->datastoreCloud,
            $this->datastoreAcli,
            $this->cloudCredentials,
            $this->telemetryHelper,
            $this->acliRepoRoot,
            $this->clientServiceProphecy->reveal(),
            $this->sshHelper,
            $this->sshDir,
            $this->logger,
            $this->selfUpdateManager,
            new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([]))]),
            $oktaConfig->reveal(),
        );
    }

    /**
     * @return array<array{array<string, string|bool>, array<string>}>
     */
    public static function providerLegacyRoutingOptions(): array
    {
        return [
            'key alone' => [['--key' => self::$key], ['no', self::$secret]],
            'secret alone' => [['--secret' => self::$secret], ['no', self::$key]],

            'use-legacy-auth alone' => [['--use-legacy-auth' => true], ['no', self::$key, self::$secret]],
        ];
    }

    /**
     * @param array<string, string|bool> $args
     * @param array<string> $inputs
     */
    #[DataProvider('providerLegacyRoutingOptions')]
    public function testCommandLineOptionsRouteToLegacyEvenWhenDeviceCodeIsConfigured(array $args, array $inputs): void
    {
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(false);
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode(['send_telemetry' => false]));
        $this->createDataStores();
        $this->command = $this->createCommand();

        $this->executeCommand($args, $inputs);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Saved credentials', $output);
        $this->assertStringNotContainsString('Sign in to Acquia ID in your browser', $output);
    }
}
