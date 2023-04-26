<?php

namespace Acquia\Cli\Exception;

use Exception;
use Zumba\Amplitude\Amplitude;

class AcquiaCliException extends Exception {

  /**
   * Object constructor. Sets context array as replacements property.
   *
   * @param string|null $raw_message
   * @param array $replacements
   *   Context array to interpolate into message.
   * @param int $code
   *   Exit code.
   */
  public function __construct(
    private ?string $raw_message = NULL,
    array $replacements = [],
    int $code = 0
    ) {
    $event_properties = [
      'code' => $code,
      'message' => $raw_message,
];
    Amplitude::getInstance()->queueEvent('Threw exception', $event_properties);

    parent::__construct($this->interpolateString($raw_message, $replacements), $code);
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
