<?php

namespace Acquia\Cli\Exception;

use Exception;

/**
 * Class AcquiaCliException.
 */
class AcquiaCliException extends Exception {

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
    $this->raw_message = $message;

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
