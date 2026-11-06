<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Auth;

use Acquia\Cli\CloudApi\AuthConfig;
use Acquia\Cli\CloudApi\DeviceTokenRefresher;
use Acquia\Cli\CloudApi\DeviceTokenRefreshResult;
use Acquia\Cli\Command\Auth\AuthLoginCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Connector\Connector;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Prophecy\Argument;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Routing tests that stub AuthConfig rather than exporting its variables.
 *
 * @property AuthLoginCommand $command
 */
class AuthLoginRoutingTest extends CommandTestBase
{
    /**
     * Empty means any authorization-server request errors, which is what the
     * legacy-routing cases rely on.
     *
     * @var array<mixed>
     */
    private array $authServerResponses = [];

    /**
     * Whether the stubbed AuthConfig reports device code as configured.
     */
    private bool $authConfigured = true;

    /**
     * Null lets the command build its own, which the routing cases rely on.
     */
    private ?DeviceTokenRefresher $deviceTokenRefresher = null;

    protected function createCommand(): CommandBase
    {
        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->useTty()->willReturn(false);
        $localMachineHelper->isBrowserAvailable()->willReturn(false);

        $authConfig = $this->prophet->prophesize(AuthConfig::class);
        $authConfig->isConfigured()->willReturn($this->authConfigured);
        $authConfig->clientId()->willReturn('client-123');
        $authConfig->domain()->willReturn('example.acquia.com');
        $authConfig->authServerId()->willReturn('ausTest');

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
            new GuzzleClient(['handler' => HandlerStack::create(new MockHandler($this->authServerResponses))]),
            $authConfig->reveal(),
            $this->deviceTokenRefresher,
        );
    }

    private function deviceAuthorizeResponse(): Response
    {
        return new Response(200, [], json_encode([
            'device_code' => 'test-device-code',
            'expires_in' => 600,
            'interval' => 0,
            'user_code' => 'ABCD1234',
            'verification_uri' => 'https://example.acquia.com/activate',
        ]));
    }

    /** A refresher that answers with the given state and makes no request. */
    private function refresherAnswering(DeviceTokenRefreshResult $result): DeviceTokenRefresher
    {
        $prophecy = $this->prophet->prophesize(DeviceTokenRefresher::class);
        $prophecy->getValidAccessToken()->willReturn($result);

        return $prophecy->reveal();
    }

    /** The access token's `expiry` is stale on purpose: it must not be read. */
    private function givenAStoredDeviceToken(): void
    {
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode([
            'device_token' => [
                'access_token' => 'existing-token',
                'client_id' => 'test-client-id',
                'expiry' => time() - 1,
                'refresh_token' => 'existing-refresh-token',
            ],
            'send_telemetry' => false,
        ]));
        $this->createDataStores();
    }


    /**
     * The defect: on `main` the same keystroke exits 0 without authenticating,
     * and every later command then fails with "Failed to authenticate".
     */
    public function testAnEndedSessionReportsExpiryAndSigningInIsTheDefault(): void
    {
        $this->givenAStoredDeviceToken();
        $this->authServerResponses = [
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 300,
                'refresh_token' => 'new-refresh-token',
            ])),
        ];
        $this->deviceTokenRefresher = $this->refresherAnswering(DeviceTokenRefreshResult::sessionEnded());
        $this->command = $this->createCommand();

        // A bare Enter -- `['']`, not `[]`: on EOF `QuestionHelper::ask()`
        // also switches interactivity off, which tests a different path.
        $this->executeCommand([], ['']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('session has expired', $output);
        $this->assertStringContainsString('Sign in again?', $output);
        $this->assertStringContainsString('Authenticated successfully', $output);
        $this->assertStringNotContainsString('Already authenticated', $output);
    }

    /**
     * A caller with no terminal and no `-n` is still treated as interactive:
     * Symfony switches on the flag, not the TTY. It reaches the question and
     * EOF answers it with the new default.
     */
    public function testACallerWithNoTerminalGetsTheDefaultAnswer(): void
    {
        $this->givenAStoredDeviceToken();
        $this->authServerResponses = [
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 300,
                'refresh_token' => 'new-refresh-token',
            ])),
        ];
        $this->deviceTokenRefresher = $this->refresherAnswering(DeviceTokenRefreshResult::sessionEnded());
        $this->command = $this->createCommand();

        // `[]`, not `['']`: an empty stream is EOF, which is what a script with
        // closed stdin gives. On `main` the default was no and this exited 0.
        $this->executeCommand([], []);
        $output = $this->getDisplay();

        $this->assertStringContainsString('session has expired', $output);
        $this->assertStringContainsString('Authenticated successfully', $output);
    }

    /** The flipped default must not become "always sign in". */
    public function testDecliningAnEndedSessionChangesNothing(): void
    {
        $this->givenAStoredDeviceToken();
        // No responses queued: reaching the flow would error.
        $this->deviceTokenRefresher = $this->refresherAnswering(DeviceTokenRefreshResult::sessionEnded());
        $this->command = $this->createCommand();

        $this->executeCommand([], ['no']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('session has expired', $output);
        $this->assertStringNotContainsString('Authenticated successfully', $output);
        $stored = json_decode(file_get_contents($this->cloudConfigFilepath), true);
        $this->assertSame('existing-refresh-token', $stored['device_token']['refresh_token']);
    }

    /** Guards against the flipped default leaking into the live branch. */
    public function testALiveSessionStillDefaultsToDeclining(): void
    {
        $this->givenAStoredDeviceToken();
        $this->deviceTokenRefresher = $this->refresherAnswering(DeviceTokenRefreshResult::token('still-good'));
        $this->command = $this->createCommand();

        $this->executeCommand([], ['']);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Already authenticated via', $output);
        $this->assertStringContainsString('Re-authenticate?', $output);
        $this->assertStringNotContainsString('session has expired', $output);
        $this->assertStringNotContainsString('Authenticated successfully', $output);
    }

    /**
     * An unreachable server is not a verdict on the credential: reporting it
     * as ended would sign a developer out because their connection faltered.
     */
    public function testAnUnreachableServerIsReportedAndDestroysNothing(): void
    {
        $this->givenAStoredDeviceToken();
        $this->deviceTokenRefresher = $this->refresherAnswering(DeviceTokenRefreshResult::serverUnreachable());
        $this->command = $this->createCommand();

        $this->executeCommand([], ['']);
        $output = $this->getDisplay();

        $this->assertStringContainsString("couldn't be reached", $output);
        $this->assertStringContainsString('Re-authenticate?', $output);
        $this->assertStringNotContainsString('session has expired', $output);
        // ACLI could not check, so it must not claim the session is live.
        $this->assertStringNotContainsString('Already authenticated', $output);
        $stored = json_decode(file_get_contents($this->cloudConfigFilepath), true);
        $this->assertSame('existing-refresh-token', $stored['device_token']['refresh_token']);
    }

    /**
     * Asserting the call is never made, rather than that no prompt appeared,
     * is the point: the latter passes even with the guard removed.
     */
    public function testNoInteractionNeverAsksTheAuthorizationServer(): void
    {
        $this->givenAStoredDeviceToken();
        $refresherProphecy = $this->prophet->prophesize(DeviceTokenRefresher::class);
        // willReturn too: the result class is final, so without a promise
        // Prophecy fails doubling the return type instead of reporting.
        $refresherProphecy->getValidAccessToken()
            ->willReturn(DeviceTokenRefreshResult::sessionEnded())
            ->shouldNotBeCalled();
        $this->authServerResponses = [
            $this->deviceAuthorizeResponse(),
            new Response(200, [], json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 300,
                'refresh_token' => 'new-refresh-token',
            ])),
        ];
        $this->deviceTokenRefresher = $refresherProphecy->reveal();
        $this->command = $this->createCommand();

        // The fourth argument is $interactive.
        $this->executeCommand([], [], OutputInterface::VERBOSITY_NORMAL, false);
        $output = $this->getDisplay();

        $this->assertStringContainsString('Signing in again via', $output);
        // Nothing checked the session, so it must not be claimed live.
        $this->assertStringNotContainsString('Already authenticated', $output);
        $this->assertStringNotContainsString('Re-authenticate?', $output);
        $this->assertStringContainsString('Authenticated successfully', $output);
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

    public function testNoCommandLineOptionsSkipsTheLegacyGuard(): void
    {
        $this->authConfigured = false;
        $this->removeMockCloudConfigFile();
        $this->fs->dumpFile($this->cloudConfigFilepath, json_encode(['send_telemetry' => false]));
        $this->createDataStores();
        $this->mockRequest('getAccount');
        $this->clientServiceProphecy->setConnector(Argument::type(Connector::class))->shouldBeCalled();
        $this->clientServiceProphecy->isMachineAuthenticated()->willReturn(false);
        $this->command = $this->createCommand();

        $this->executeCommand([], ['no', self::$key, self::$secret]);

        $this->assertStringContainsString('Device code sign-in is not configured', $this->getDisplay());
    }

    /**
     * Cancelling a sign-in must not look like a completed one.
     *
     * Symfony dispatches SIGINT through `ConsoleSignalEvent` and exits with the
     * event's code, which defaults to 0 when no command claims the signal, so
     * an agent that kills `auth:login` mid-poll would read an abandoned
     * sign-in as an authenticated session.
     */
    #[RequiresPhpExtension('pcntl')]
    public function testItClaimsTheSignalsThatWouldOtherwiseExitZero(): void
    {
        $this->assertSame([SIGINT, SIGTERM], $this->command->getSubscribedSignals());
    }

    #[RequiresPhpExtension('pcntl')]
    public function testCancellingASignInExitsOneHundredAndThirty(): void
    {
        $output = new BufferedOutput();
        (new ReflectionProperty($this->command, 'output'))->setValue($this->command, $output);

        // 128 + SIGINT, the shell's convention for a signalled command.
        $this->assertSame(130, $this->command->handleSignal(SIGINT));
        $this->assertSame(PHP_EOL . 'Sign-in cancelled. No credentials were stored.' . PHP_EOL, $output->fetch());
    }

    #[RequiresPhpExtension('pcntl')]
    public function testSigtermAlsoExitsNonZero(): void
    {
        (new ReflectionProperty($this->command, 'output'))->setValue($this->command, new BufferedOutput());

        $this->assertSame(128 + SIGTERM, $this->command->handleSignal(SIGTERM));
    }

    /**
     * Symfony registers the handler before the command runs, so a signal can
     * arrive while the output is still uninitialised.
     */
    #[RequiresPhpExtension('pcntl')]
    public function testASignalBeforeTheCommandStartsDoesNotFatal(): void
    {
        $command = (new ReflectionClass(AuthLoginCommand::class))->newInstanceWithoutConstructor();

        $this->assertSame(130, $command->handleSignal(SIGINT));
    }
}
