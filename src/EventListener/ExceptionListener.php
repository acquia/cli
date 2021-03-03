<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class ExceptionListener {

  /**
   * @var string
   */
  private $messagesBgColor;

  /**
   * @var string[]
   */
  private $helpMessages = [];

  /**
   * @param \Symfony\Component\Console\Event\ConsoleErrorEvent $event
   */
  public function onConsoleError(ConsoleErrorEvent $event): void {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    $errorMessage = $error->getMessage();
    $this->messagesBgColor = 'blue';

    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $new_error_message = 'Your Cloud Platform API credentials are invalid.';
      $this->helpMessages[] = "Run <bg={$this->messagesBgColor};options=bold>acli auth:login</> to reset your API credentials.";
    }

    if ($error instanceof RuntimeException) {
      switch ($errorMessage) {
        case 'Not enough arguments (missing: "environmentId").':
        case 'Not enough arguments (missing: "environmentUuid").':
          $this->writeSiteAliasHelp();
          break;
        case 'Not enough arguments (missing: "applicationUuid").':
          $this->writeApplicationAliasHelp();
          break;
      }
    }

    if ($error instanceof AcquiaCliException) {
      switch ($errorMessage) {
        case '{applicationUuid} must be a valid UUID or application alias.':
          $this->writeApplicationAliasHelp();
          break;
        case '{environmentId} must be a valid UUID or site alias.':
        case '{environmentUuid} must be a valid UUID or site alias.':
          $this->writeSiteAliasHelp();
          break;
      }
    }

    if ($error instanceof ApiErrorException) {
      switch ($errorMessage) {
        case "There are no available Cloud IDEs for this application.\n":
          $this->helpMessages[] = "Delete an existing IDE via <bg={$this->messagesBgColor};options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.";
          $this->writeSupportTicketHelp();
          break;
        default:
          $new_error_message = 'Cloud Platform API returned an error: ' . $errorMessage;
      }
      $this->helpMessages[] = "You can learn more about Cloud Platform API at https://docs.acquia.com/cloud-platform/develop/api/";
    }

    $this->helpMessages[] = "You can find Acquia CLI documentation at https://docs.acquia.com/acquia-cli/";
    /** @var \Acquia\Cli\Application $application */
    $application = $event->getCommand()->getApplication();
    $application->setHelpMessages($this->helpMessages);

    if (isset($new_error_message)) {
      $event->setError(new AcquiaCliException($new_error_message, [], $exitCode));
    }
  }

  /**
   *
   */
  protected function writeApplicationAliasHelp(): void {
    $this->helpMessages[] = "<bg={$this->messagesBgColor};options=bold>applicationUuid</> can also be an application alias. E.g. <bg={$this->messagesBgColor};options=bold>myapp</>." . PHP_EOL
      . "Run <bg={$this->messagesBgColor};options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   *
   */
  protected function writeSiteAliasHelp(): void {
    $this->helpMessages[] = "<bg={$this->messagesBgColor};options=bold>environmentId</> can also be a site alias. E.g. <bg={$this->messagesBgColor};options=bold>myapp.dev</>." . PHP_EOL
    . "Run <bg={$this->messagesBgColor};options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   *
   */
  protected function writeSupportTicketHelp(): void {
    $this->helpMessages[] = "You may also ask for more information at" . PHP_EOL
    . "https://insight.acquia.com/support/tickets/new?product=p:ride";
  }

}
