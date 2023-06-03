<?php

declare(strict_types = 1);

namespace Acquia\Cli\Exception;

use Exception;
use Zumba\Amplitude\Amplitude;

class AcquiaCliException extends Exception {

  /**
   * Object constructor. Sets context array as replacements property.
   *
   * @param array $replacements
   *   Context array to interpolate into message.
   * @param int $code
   *   Exit code.
   */
  public function __construct(
    private ?string $rawMessage = NULL,
    array $replacements = [],
    int $code = 0
    ) {
    $eventProperties = [
      'code' => $code,
      'message' => $rawMessage,
];
    Amplitude::getInstance()->queueEvent('Threw exception', $eventProperties);

    parent::__construct($this->interpolateString($rawMessage, $replacements), $code);
  }

  /**
   * Returns the replacements context array.
   *
   * @return string $this->replacements
   */
  public function getRawMessage(): string {
    return $this->rawMessage;
  }

  /**
   * Replace the variables into the message string.
   *
   * @param array|string $message
   *   The raw, uninterpolated message string.
   * @param array $replacements
   *   The values to replace into the message.
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
