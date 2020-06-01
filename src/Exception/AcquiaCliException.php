<?php

namespace Acquia\Cli\Exception;

use Exception;
use Zumba\Amplitude\Amplitude;

/**
 * Class AcquiaCliException.
 */
class AcquiaCliException extends Exception {
  /**
   * @var array
   */
  private $replacements;

  /**
   * @var null|string
   */
  private $raw_message;

  /**
   * Object constructor. Sets context array as replacements property.
   *
   * @param string $message
   *   Message to send when throwing the exception.
   * @param array $replacements
   *   Context array to interpolate into message.
   * @param int $code
   *   Exit code.
   */
  public function __construct(
        $message = NULL,
        array $replacements = [],
        $code = 0
    ) {
    $this->replacements = $replacements;
    $this->raw_message = $message;

    $event_properties = [
      'message' => $message,
      'code' => $code
    ];
    Amplitude::getInstance()->queueEvent('Threw exception', $event_properties);

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
   * @param string|array $message
   *   The raw, uninterpolated message string.
   * @param array $replacements
   *   The values to replace into the message.
   *
   * @return string
   */
  protected function interpolateString($message, $replacements): string {
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
