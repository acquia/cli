<?php

declare(strict_types=1);

namespace AcquiaMigrate\Safety;

use Closure;
use DomainException;

/**
 * Helper for defining and using array validation.
 */
trait ArrayValidationTrait {

  /**
   * Creates a schema validation function.
   *
   * @param array $schema
   *   The schema definition for the array to be validated.
   * @param array $defaults
   *   Defaults for the array to be validated.
   *
   * @return \AcquiaMigrate\Safety\StructuredArrayValidator
   *   An array validator.
   */
  protected static function schema(array $schema, array $defaults = []) : StructuredArrayValidator {
    return StructuredArrayValidator::create($schema, $defaults);
  }

  /**
   * Creates a conditional schema validation function for a child element.
   *
   * @param array $schema
   *   The schema definition for the array to be validated.
   * @param callable $conditional
   *   A callable which receives the context array and returns TRUE if the
   *   schema should be applied, or FALSE otherwise. If FALSE, the element will
   *   be omitted from the final validated array.
   * @param array $defaults
   *   Defaults for the array to be validated.
   *
   * @return \AcquiaMigrate\Safety\StructuredArrayValidator
   *   An array validator.
   */
  protected static function conditionalSchema(array $schema, callable $conditional, array $defaults = []) : StructuredArrayValidator {
    return StructuredArrayValidator::createConditionalValidator($schema, $conditional, $defaults);
  }

  /**
   * Creates a validator for an indexed array of a given type.
   *
   * @param callable $item_validator
   *   A validator to apply to each item in a validated array.
   *
   * @return \Closure
   *   A validation function.
   */
  protected static function listOf(callable $item_validator) : Closure {
    return static::arrayOf('is_int', $item_validator);
  }

  /**
   * Creates a validator for an associative array with arbitrary string as keys.
   *
   * @param callable $entry_validator
   *   A validator to apply to each entry in a validated array.
   *
   * @return \Closure
   *   A validation function.
   */
  protected static function dictionaryOf(callable $entry_validator) : Closure {
    return static::arrayOf('is_string', $entry_validator);
  }

  /**
   * Creates an arbitrarily keyed array validator.
   *
   * @param callable $key_validator
   *   A callable, either 'is_int' or 'is_string' to check against each key of
   *   the given array.
   * @param callable $value_validator
   *   A callable to evaluate against each value in the given array.
   *
   * @return \Closure
   *   A validation function.
   */
  private static function arrayOf(callable $key_validator, callable $value_validator) {
    assert(in_array($key_validator, ['is_int', 'is_string'], TRUE));
    return Closure::fromCallable(function ($values) use ($key_validator, $value_validator) {
      if (!is_array($values)) {
        throw new DomainException('Validated value is not an array.');
      }
      foreach ($values as $index => $value) {
        if (!$key_validator($index)) {
          throw new DomainException("The array key `$index` must be an integer or a string.");
        }
        elseif ($value_validator instanceof StructuredArrayValidator || $value_validator instanceof Closure) {
          $values[$index] = $value_validator($value);
        }
        elseif (!call_user_func_array($value_validator, [$value])) {
          throw new DomainException('Failed to validate value.');
        }
      }
      return $values;
    });
  }

}
