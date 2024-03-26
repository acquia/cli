<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Application;
use Acquia\Cli\EventListener\ExceptionListener;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\TestBase;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\RuntimeException;

class ExceptionListenerTest extends TestBase {

  /**
   * @dataProvider providerTestHelp
   */
  public function testHelp(\Throwable $error, string $helpText): void {
    $exceptionListener = new ExceptionListener();
    $commandProphecy = $this->prophet->prophesize(Command::class);
    $applicationProphecy = $this->prophet->prophesize(Application::class);
    $messages = [$helpText, "You can find Acquia CLI documentation at https://docs.acquia.com/acquia-cli/", "You can submit a support ticket at https://support-acquia.force.com/s/contactsupport\nRe-run the command with the <bg=blue;fg=white;options=bold>-vvv</> flag and include the full command output in your support ticket."];
    $applicationProphecy->setHelpMessages($messages)->shouldBeCalled();
    $commandProphecy->getApplication()->willReturn($applicationProphecy->reveal());
    $consoleErrorEvent = new ConsoleErrorEvent($this->input, $this->output, $error, $commandProphecy->reveal());
    $exceptionListener->onConsoleError($consoleErrorEvent);
    $this->prophet->checkPredictions();
    self::assertTrue(TRUE);
  }

  /**
   * @return string[][]
   */
  public function providerTestHelp(): array {
    return [
      [
        new IdentityProviderException('invalid_client', 0, ''),
        'Run <bg=blue;fg=white;options=bold>acli auth:login</> to reset your API credentials.',
      ],
      [
        new RuntimeException('Not enough arguments (missing: "environmentId").'),
        "<bg=blue;options=bold>environmentId</> can also be a site alias. E.g. <bg=blue;fg=white;options=bold>myapp.dev</>.\nRun <bg=blue;options=bold>acli remote:aliases:list</> to see a list of all available aliases.",
      ],
      [
        new RuntimeException('Not enough arguments (missing: "environmentUuid").'),
        "<bg=blue;options=bold>environmentId</> can also be a site alias. E.g. <bg=blue;fg=white;options=bold>myapp.dev</>.\nRun <bg=blue;options=bold>acli remote:aliases:list</> to see a list of all available aliases.",
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
        new AcquiaCliException('Access token file not found at {file}'),
        'Get help for this error at https://docs.acquia.com/ide/known-issues/#the-automated-cloud-platform-api-authentication-might-fail',
      ],
    ];
  }

}
