<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Safety;

use Closure;
use DomainException;
use Exception;

/**
 * A callable object which validates a given array.
 */
final class StructuredArrayValidator
{
    /**
     * Schema definition key that matches an associative array w/ arbitrary keys.
     */
    protected const KEYS_ARE_STRINGS = __CLASS__ . '%array_keys_are_string%';

    /**
     * A schema definition for the array to be validated.
     *
     * @var array<mixed>
     */
    protected array $schema;

    /**
     * A set of defaults for the array to be validated.
     *
     * @var array<mixed>
     */
    protected array $defaults;

    /**
     * Whether the schema is conditional or not.
     *
     * @var bool|callable
     */
    protected $conditional;

    /**
     * ArrayValidator constructor.
     *
     * @param array $schema
     *   A schema definition for the array to be validated.
     * @param array $defaults
     *   A set of defaults for the array to be validated.
     * @param bool|\Closure $conditional
     *   A callable or FALSE. See self::createChildValidator().
     */
    protected function __construct(array $schema, array $defaults, bool|\Closure $conditional)
    {
        assert(!isset($schema[static::KEYS_ARE_STRINGS]) || empty(array_diff_key($schema, array_flip([static::KEYS_ARE_STRINGS]))), 'A schema must contain either the KEYS_ARE_STRINGS constant or validations for specific array keys, but not both.');
        assert($conditional === false || $conditional instanceof Closure);
        $this->schema = $schema;
        $this->defaults = $defaults;
        $this->conditional = $conditional;
    }

    /**
     * Creates a new ArrayValidator.
     *
     * @param array $schema
     *   A schema definition for the array to be validated.
     * @param array $defaults
     *   A set of defaults for the array to be validated.
     * @return static
     *   A new Array validator.
     */
    public static function create(array $schema, array $defaults = []): static
    {
        return new static($schema, $defaults, false);
    }

    /**
     * Creates a new ArrayValidator.
     *
     * Note: only child validators, that is, validators that are children of
     * another validator should be conditional.
     *
     * @param array $schema
     *   A schema definition for the array to be validated.
     * @param \Closure $conditional
     *   The function should be a function that receives the context array and
     *   returns a bool. If TRUE, the validation will be applied, otherwise it
     *   will be skipped and the value will be omitted from the final validated
     *   array.
     * @param array $defaults
     *   A set of defaults for the array to be validated.
     * @return static
     *   A new Array validator.
     */
    public static function createConditionalValidator(array $schema, Closure $conditional, array $defaults = []): static
    {
        return new static($schema, $defaults, $conditional);
    }

    /**
     * Validates and returns a validated array.
     *
     * @param mixed $arr
     *   An array to validate.
     * @return array<mixed>
     *   If the given $arr is valid, the given $arr value. Array keys not defined
     *   in the schema definition will be stripped from return value.
     */
    public function __invoke(mixed $arr): array
    {
        if (!is_array($arr)) {
            throw new DomainException('Validated value is not an array.');
        }
        $arr += $this->defaults;
        // The schema must define expected keys, so validate each key accordingly.
        foreach ($this->schema as $schema_key => $validation) {
            // Validate in every case except where the validation is conditional and
            // the condition does *not* evaluate to TRUE.
            $should_validate = !$validation instanceof self || !$validation->isConditional() || ($validation->conditional)($arr);
            if ($should_validate && !array_key_exists($schema_key, $arr)) {
                throw new DomainException("Missing required key: $schema_key");
            } else {
                // If the validation does not apply, omit the value from the validated
                // return value.
                if ($validation instanceof self && !$should_validate) {
                    unset($arr[$schema_key]);
                } else {
                    if ($validation instanceof self || $validation instanceof Closure) {
                        $arr[$schema_key] = $validation($arr[$schema_key]);
                    } elseif (!call_user_func_array($validation, [$arr[$schema_key]])) {
                        throw new DomainException('Failed to validate value.');
                    }
                }
            }
        }
        return array_intersect_key($arr, $this->schema);
    }

    /**
     * Whether the given argument is valid.
     *
     * @param mixed $arr
     *   An array to be validated.
     * @return bool
     *   TRUE if the argument is valid; FALSE otherwise.
     */
    public function isValid(mixed $arr): bool
    {
        try {
            $this($arr);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Whether the validator is conditional.
     *
     * @return bool
     *   TRUE if the validator may or not be applied, depending on context. FALSE
     *   if the validator will be applied unconditionally.
     */
    public function isConditional(): bool
    {
        return (bool) $this->conditional;
    }
}
