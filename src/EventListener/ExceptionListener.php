<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class ExceptionListener
{

  public function onConsoleError(ConsoleErrorEvent $event) {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $event->setError(new AcquiaCliException('Your Cloud API credentials are invalid. Run acli auth:login to reset them.',
        [], $exitCode));
    }
  }

}
