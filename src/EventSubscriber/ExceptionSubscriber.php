<?php

namespace Acquia\Cli\EventSubscriber;

use Acquia\Cli\Exception\AcquiaCliException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      ConsoleEvents::ERROR => [
        ['processException', 10],
      ],
      ConsoleEvents::COMMAND => [
        ['processCommand', 10],
      ],
      ConsoleEvents::TERMINATE => [
        ['processTerminate', 10],
      ],
    ];
  }

  public function processException(ConsoleErrorEvent $event) {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $event->setError(new AcquiaCliException('Your Cloud API credentials are invalid. Run acli auth:login to reset them.',
        [], $exitCode));
    }
  }

  public function processCommand(ConsoleEvent $event) {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $event->setError(new AcquiaCliException('Your Cloud API credentials are invalid. Run acli auth:login to reset them.',
        [], $exitCode));
    }
  }

  public function processTerminate(ConsoleEvent $event) {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $event->setError(new AcquiaCliException('Your Cloud API credentials are invalid. Run acli auth:login to reset them.',
        [], $exitCode));
    }
  }

}
