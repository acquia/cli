<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Application;
use Acquia\Cli\EventListener\ExceptionListener;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\TestBase;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\RuntimeException;
use Throwable;

class ExceptionListenerTest extends TestBase
{
    private static string $siteAliasHelp = '<bg=blue;options=bold>environmentId</> can also be a site alias. E.g. <bg=blue;fg=white;options=bold>myapp.dev</>.' . PHP_EOL . 'Run <bg=blue;options=bold>acli remote:aliases:list</> to see a list of all available aliases.';
    private static string $appAliasHelp = 'The <bg=blue;options=bold>applicationUuid</> argument must be a valid UUID or unique application alias accessible to your Cloud Platform user.' . PHP_EOL . PHP_EOL . 'An alias consists of an application name optionally prefixed with a hosting realm, e.g. <bg=blue;fg=white;options=bold>myapp</> or <bg=blue;fg=white;options=bold>prod.myapp</>.' . PHP_EOL . PHP_EOL . 'Run <bg=blue;options=bold>acli remote:aliases:list</> to see a list of all available aliases.';

    /**
     * @dataProvider providerTestHelp
     */
    public function testHelp(Throwable $error, string|array $helpText): void
    {
        $exceptionListener = new ExceptionListener();
        $commandProphecy = $this->prophet->prophesize(Command::class);
        $applicationProphecy = $this->prophet->prophesize(Application::class);
        $messages1 = ['You can find Acquia CLI documentation at https://docs.acquia.com/acquia-cli/', 'You can submit a support ticket at https://support-acquia.force.com/s/contactsupport' . PHP_EOL . 'Re-run the command with the <bg=blue;fg=white;options=bold>-vvv</> flag and include the full command output in your support ticket.'];
        if (is_array($helpText)) {
            $messages = array_merge($helpText, $messages1);
        } else {
            $messages = array_merge([$helpText], $messages1);
        }
        $messages[0] = "<options=bold>How to fix it:</> $messages[0]";
        $applicationProphecy->setHelpMessages($messages)->shouldBeCalled();
        $commandProphecy->getApplication()->willReturn($applicationProphecy->reveal());
        $consoleErrorEvent = new ConsoleErrorEvent($this->input, $this->output, $error, $commandProphecy->reveal());
        $exceptionListener->onConsoleError($consoleErrorEvent);
        $this->prophet->checkPredictions();
        self::assertTrue(true);
    }

    /**
     * @return string[][]
     */
    public function providerTestHelp(): array
    {
        return [
        [
        new IdentityProviderException('invalid_client', 0, ''),
        'Run <bg=blue;fg=white;options=bold>acli auth:login</> to reset your API credentials.',
        ],
        [
        new RuntimeException('Not enough arguments (missing: "environmentId").'),
        self::$siteAliasHelp,
        ],
        [
        new RuntimeException('Not enough arguments (missing: "environmentUuid").'),
        self::$siteAliasHelp,
        ],
        [
        new AcquiaCliException('No applications match the alias {applicationAlias}'),
        self::$appAliasHelp,
        ],
        [
        new AcquiaCliException('Multiple applications match the alias {applicationAlias}'),
        self::$appAliasHelp,
        ],
        [
        new AcquiaCliException('{environmentId} must be a valid UUID or site alias.'),
        self::$siteAliasHelp,
        ],
        [
        new AcquiaCliException('{environmentUuid} must be a valid UUID or site alias.'),
        self::$siteAliasHelp,
        ],
        [
        new AcquiaCliException('Access token file not found at {file}'),
        'Get help for this error at https://docs.acquia.com/ide/known-issues/#the-automated-cloud-platform-api-authentication-might-fail',
        ],
        [
        new AcquiaCliException('Access token expiry file not found at {file}'),
        'Get help for this error at https://docs.acquia.com/ide/known-issues/#the-automated-cloud-platform-api-authentication-might-fail',
        ],
        [
        new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform.'),
        'Run `acli auth:login` to re-authenticated with the Cloud Platform.',
        ],
        [
        new AcquiaCliException('This machine is not yet authenticated with Site Factory.'),
        'Run `acli auth:acsf-login` to re-authenticate with Site Factory.',
        ],
        [
        new AcquiaCliException('Could not extract aliases to {destination}'),
        'Check that you have write access to the directory',
        ],
        [
        new ApiErrorException((object) ['error' => '', 'message' => "There are no available Cloud IDEs for this application.\n"]),
        'Delete an existing IDE via <bg=blue;fg=white;options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.',
        ],
        [
        new ApiErrorException((object) ['error' => '', 'message' => 'This resource requires additional authentication.']),
        ['This is likely because you have Federated Authentication required for your organization.', 'Run `acli login` to authenticate via API token and then try again.'],
        ],
        [
        new ApiErrorException((object) ['error' => 'asdf', 'message' => 'fdsa']),
        'You can learn more about Cloud Platform API at https://docs.acquia.com/cloud-platform/develop/api/',
        ],
        ];
    }
}
