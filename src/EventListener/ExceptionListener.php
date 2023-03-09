<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Make exceptions warm and cuddly.
 *
 * Vendor libraries and APIs throw exceptions that aren't very helpful on their
 * own. We can rewrite them to be more helpful and add support links.
 */
class ExceptionListener {

  private string $messagesBgColor = 'blue';

  private string $messagesFgColor = 'white';

  /**
   * @var string[]
   */
  private array $helpMessages = [];

  /**
   */
  public function onConsoleError(ConsoleErrorEvent $event): void {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    $errorMessage = $error->getMessage();

    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $new_error_message = 'Your Cloud Platform API credentials are invalid.';
      $this->helpMessages[] = "Run <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>acli auth:login</> to reset your API credentials.";
    }

    if ($error instanceof RuntimeException) {
      switch ($errorMessage) {
        case 'Not enough arguments (missing: "environmentId").':
        case 'Not enough arguments (missing: "environmentUuid").':
          $this->writeSiteAliasHelp();
          break;
      }
    }

    if ($error instanceof AcquiaCliException) {
      switch ($error->getRawMessage()) {
        case 'No applications match the alias {applicationAlias}':
        case 'Multiple applications match the alias {applicationAlias}':
          $this->writeApplicationAliasHelp();
          break;
        case '{environmentId} must be a valid UUID or site alias.':
        case '{environmentUuid} must be a valid UUID or site alias.':
          $this->writeSiteAliasHelp();
          break;
        case 'Access token file not found at {file}':
        case 'Access token expiry file not found at {file}':
          $this->helpMessages[] = 'Get help for this error at https://docs.acquia.com/ide/known-issues/#the-automated-cloud-platform-api-authentication-might-fail';
          break;
      }
    }

    if ($error instanceof ApiErrorException) {
      switch ($errorMessage) {
        case "There are no available Cloud IDEs for this application.\n":
          $this->helpMessages[] = "Delete an existing IDE via <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.";
          break;
        case "This resource requires additional authentication.":
          $this->helpMessages[] = "This is likely because you have Federated Authentication required for your organization.";
          $this->helpMessages[] = "Run `acli login` to authenticate via API token and then try again.";
          break;
        default:
          $new_error_message = 'Cloud Platform API returned an error: ' . $errorMessage;
          $this->helpMessages[] = "You can learn more about Cloud Platform API at https://docs.acquia.com/cloud-platform/develop/api/";
      }
    }

    $this->helpMessages[] = "You can find Acquia CLI documentation at https://docs.acquia.com/acquia-cli/";
    $this->writeUpdateHelp($event);
    $this->writeSupportTicketHelp($event);

    if ($command = $event->getCommand()) {
      /** @var \Acquia\Cli\Application $application */
      $application = $command->getApplication();
      $application->setHelpMessages($this->helpMessages);
    }

    if (isset($new_error_message)) {
      $event->setError(new AcquiaCliException($new_error_message, [], $exitCode));
    }
  }

  /**
   *
   */
  private function writeApplicationAliasHelp(): void {
    $this->helpMessages[] = "The <bg=$this->messagesBgColor;options=bold>applicationUuid</> argument must be a valid UUID or unique application alias accessible to your Cloud Platform user." . PHP_EOL . PHP_EOL
      . "An alias consists of an application name optionally prefixed with a hosting realm, e.g. <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>myapp</> or <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>prod.myapp</>." . PHP_EOL . PHP_EOL
      . "Run <bg=$this->messagesBgColor;options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   *
   */
  private function writeSiteAliasHelp(): void {
    $this->helpMessages[] = "<bg=$this->messagesBgColor;options=bold>environmentId</> can also be a site alias. E.g. <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>myapp.dev</>." . PHP_EOL
    . "Run <bg=$this->messagesBgColor;options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   */
  private function writeSupportTicketHelp(ConsoleErrorEvent $event): void {
    $message = "You can submit a support ticket at https://support-acquia.force.com/s/contactsupport";
    if (!$event->getOutput()->isVeryVerbose()) {
      $message .= PHP_EOL . "Re-run the command with the <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>-vvv</> flag and include the full command output in your support ticket.";
    }
    $this->helpMessages[] = $message;
  }

  /**
   */
  private function writeUpdateHelp(ConsoleErrorEvent $event): void {
    try {
      $command = $event->getCommand();
      if ($command
        && method_exists($command, 'checkForNewVersion')
        && $latest = $command->checkForNewVersion()
      ) {
        $message = "Acquia CLI $latest is available. Try updating via <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>acli self-update</> and then run the command again.";
        $this->helpMessages[] = $message;
      }
      // This command may not exist during some testing.
    }
    catch (CommandNotFoundException) {
    }
  }

}
