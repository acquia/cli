<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class ExceptionListener
{

  public function onConsoleError(ConsoleErrorEvent $event) {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    $errorMessage = $error->getMessage();

    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $newErrorMessage = 'Your Cloud Platform API credentials are invalid. Run acli auth:login to reset them.';
    }

    if ($error instanceof ApiErrorException) {
      switch ($errorMessage) {
        case "There are no available Cloud IDEs for this application.\n":
          $newErrorMessage = $errorMessage . "Delete an existing IDE (acli ide:delete) or contact your Account Manager or Acquia Sales to purchase additional IDEs.\n You may also submit a support ticket to ask for more information (https://insight.acquia.com/support/tickets/new?product=p:ride)";
          break;
        // @todo Remove after CXAPI-8261 is closed.
        case "The Cloud IDE is being deleted.\n":
          $newErrorMessage = "The Cloud IDE will be deleted momentarily. This process usually takes a few minutes.\n";
          break;
        default:
          $newErrorMessage = 'Cloud Platform API returned an error: ' . $errorMessage;
      }
    }

    if (isset($newErrorMessage)) {
      $event->setError(new AcquiaCliException($newErrorMessage, [], $exitCode));
    }
  }

}
