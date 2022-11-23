<?php

namespace Acquia\Cli\Exception;

use Bugsnag\Client;
use Bugsnag\Handler;
use Exception;
use Zumba\Amplitude\Amplitude;

/**
 * Class AcquiaCliException.
 */
class AcquiaCliException extends Exception {
  /**
   * @var array
   */
  private array $replacements;

  /**
   * @var null|string
   */
  private ?string $raw_message;

  /**
   * Object constructor. Sets context array as replacements property.
   *
   * @param string|null $message
   *   Message to send when throwing the exception.
   * @param array $replacements
   *   Context array to interpolate into message.
   * @param int $code
   *   Exit code.
   */
  public function __construct(
    string $message = NULL,
    array $replacements = [],
    int $code = 0
    ) {
    $this->replacements = $replacements;
    $this->raw_message = $message;

    $event_properties = [
      'message' => $message,
      'code' => $code
    ];
    Amplitude::getInstance()->queueEvent('Threw exception', $event_properties);
    // It's safe-ish to make this key public.
    // @see https://github.com/bugsnag/bugsnag-js/issues/595
    $bugsnag = Client::make('7b8b2f87d710e3ab29ec0fd6d9ca0474');
    Handler::register($bugsnag);
    $bugsnag->notifyException(new \RuntimeException($message));

    parent::__construct($this->interpolateString($message, $replacements), $code);
  }

  /**
   * Returns the replacements context array.
   *
   * @return string $this->replacements
   */
  public function getRawMessage(): string {
    return $this->raw_message;
  }

  /**
   * Returns the replacements context array.
   *
   * @return array $this->replacements The replacement variables.
   */
  public function getReplacements(): array {
    return $this->replacements;
  }

  /**
   * Replace the variables into the message string.
   *
   * @param array|string $message
   *   The raw, uninterpolated message string.
   * @param array $replacements
   *   The values to replace into the message.
   *
   * @return string
   */
  protected function interpolateString(array|string $message, array $replacements): string {
    $tr = [];
    foreach ($replacements as $key => $val) {
      $tr['{' . $key . '}'] = $val;
    }
    if (is_array($message)) {
      $message = implode(PHP_EOL, $message);
    }

    return strtr($message, $tr);
  }

}
