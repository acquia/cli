<?php

declare(strict_types=1);

namespace AcquiaMigrate\Recommendation;

use AcquiaMigrate\ExtensionInterface;
use AcquiaMigrate\NormalizableInterface;
use AcquiaMigrate\RecommendationInterface;
use AcquiaMigrate\Safety\ArrayValidationTrait;
use Closure;
use Exception;
use LogicException;

/**
 * Represents a recommendation *not* to require a package.
 */
class AbandonmentRecommendation implements RecommendationInterface, NormalizableInterface {

  use ArrayValidationTrait;

  /**
   * An anonymous function that determines if this recommendation is applicable.
   *
   * @var \Closure
   */
  protected $evaluateExtension;

  /**
   * The original decoded definition.
   *
   * @var array
   */
  protected $definition;

  /**
   * An array of extensions to which this recommendation applied.
   *
   * @var \AcquiaMigrate\ExtensionInterface[]
   */
  protected $appliedTo = [];

  /**
   * AbandonmentRecommendation constructor.
   *
   * @param \Closure $extension_evaluator
   *   An anonymous function that determines if this recommendation applies to
   *   an extension.
   * @param array $definition
   *   The original decoded definition.
   */
  protected function __construct(Closure $extension_evaluator, array $definition) {
    $this->evaluateExtension = $extension_evaluator;
    $this->definition = $definition;
  }

  /**
   * Creates a new recommendation.
   *
   * @param mixed $definition
   *   A static recommendation definition. This must be an array. However, other
   *   value types are accepted because this method performs validation on the
   *   given value.
   *
   * @return \AcquiaMigrate\RecommendationInterface
   *   A new AbandonmentRecommendation object if the given definition is valid or
   *   a new NoRecommendation object otherwise.
   */
  public static function createFromDefinition($definition): RecommendationInterface {
    $validator = static::schema([
      'package' => 'is_null',
      'note' => 'is_string',
      'replaces' => static::schema([
        'name' => 'is_string',
      ]),
      'vetted' => 'is_bool',
    ]);
    try {
      $validated = $validator($definition);
    }
    catch (Exception $e) {
      // Under any circumstance where the given recommendation configuration is
      // invalid, we still want the rest of the script to proceed. I.e. it's
      // better to produce a valid composer.json with a missing recommendation
      // than to fail to create one at all.
      return new NoRecommendation();
    }
    return new static(Closure::fromCallable(function (ExtensionInterface $extension) use ($validated) : bool {
      return $extension->getName() === $validated['replaces']['name'];
    }), $validated);
  }

  /**
   * {@inheritDoc}
   */
  public function applies(ExtensionInterface $extension): bool {
    if (($this->evaluateExtension)($extension)) {
      array_push($this->appliedTo, $extension);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageName() : string {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a % class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function getVersionConstraint() : string {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function hasModulesToInstall() : bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function getModulesToInstall() : array {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function hasPatches() : bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function isVetted() : bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function getPatches() : array {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritDoc}
   */
  public function normalize(): array {
    $normalized = [
      'type' => 'abandonmentRecommendation',
      'id' => "abandon:{$this->definition['replaces']['name']}",
      'attributes' => [
        'note' => $this->definition['note'],
      ],
    ];

    $recommended_for = [
      'data' => array_map(function (ExtensionInterface $extension) {
        return [
          'type' => $extension->isModule() ? 'module' : 'theme',
          'id' => $extension->getName(),
        ];
      }, $this->appliedTo),
    ];
    if (!empty($recommended_for['data'])) {
      $normalized['relationships']['recommendedFor'] = $recommended_for;
    }

    return $normalized;
  }

}
