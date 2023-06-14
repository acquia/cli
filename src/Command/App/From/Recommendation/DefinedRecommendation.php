<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Recommendation;

use Acquia\Cli\Command\App\From\Safety\ArrayValidationTrait;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use Closure;
use Exception;

/**
 * Represents a recommendation defined statically in configuration.
 *
 * In future, some recommendations might be dynamic.
 */
class DefinedRecommendation implements RecommendationInterface, NormalizableInterface {

  use ArrayValidationTrait;

  /**
   * Default note value.
   *
   * @const string
   */
  const NOTE_PLACEHOLDER_STRING = '%note%';

  /**
   * An anonymous function that determines if this recommendation is applicable.
   *
   * @var \Closure
   */
  protected $evaluateExtension;

  /**
   * The name of a recommended package.
   *
   * @var string
   */
  protected $packageName;

  /**
   * A recommended composer version constraint.
   *
   * @var string
   */
  protected $versionConstraint;

  /**
   * A list of recommended modules to install.
   *
   * @var string[]
   */
  protected $install;

  /**
   * Whether this is a vetted recommendation.
   *
   * @var bool
   */
  protected $vetted;

  /**
   * A note about the recommendation.
   *
   * @var string
   */
  protected $note;

  /**
   * A list of recommended patches.
   *
   * The keys of the array should be descriptions of the patch contents and the
   * values should be URLs where the recommended patch can be downloaded.
   *
   * @var array
   */
  protected $patches;

  /**
   * An array of extensions to which this recommendation applied.
   *
   * @var \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface[]
   */
  protected $appliedTo = [];

  /**
   * DefinedRecommendation constructor.
   *
   * @param \Closure $extension_evaluator
   *   An anonymous function that determines if this recommendation applies to
   *   an extension.
   * @param string $package_name
   *   The name of the package recommended by this object.
   * @param string $version_constraint
   *   The version constraint recommended by this object.
   * @param string[] $install
   *   A list of recommended modules to install.
   * @param bool $vetted
   *   Whether this is a vetted recommendation.
   * @param string $note
   *   A note about the recommendation.
   * @param array $patches
   *   An array of patch recommendations.
   */
  protected function __construct(Closure $extension_evaluator, string $package_name, string $version_constraint, array $install, bool $vetted, string $note, array $patches = []) {
    $this->evaluateExtension = $extension_evaluator;
    $this->packageName = $package_name;
    $this->versionConstraint = $version_constraint;
    $this->install = $install;
    $this->vetted = $vetted;
    $this->note = $note;
    $this->patches = $patches;
  }

  /**
   * Creates a new recommendation.
   *
   * @param mixed $definition
   *   A static recommendation definition. This must be an array. However, other
   *   value types are accepted because this method performs validation on the
   *   given value.
   *
   * @return \Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface
   *   A new DefinedRecommendation object if the given definition is valid or
   *   a new NoRecommendation object otherwise.
   */
  public static function createFromDefinition($definition) : RecommendationInterface {
    $defaults = [
      'universal' => FALSE,
      'patches' => [],
      'install' => [],
      'vetted' => FALSE,
      'note' => static::NOTE_PLACEHOLDER_STRING,
    ];
    $validate_if_universal_is_false = Closure::fromCallable(function ($context) {
      return $context['universal'] === FALSE;
    });

    if (is_array($definition) && array_key_exists('package', $definition) && is_null($definition['package'])) {
      return AbandonmentRecommendation::createFromDefinition($definition);
    }

    $validator = static::schema([
      'universal' => 'is_bool',
      'install' => static::listOf('is_string'),
      'package' => 'is_string',
      'constraint' => 'is_string',
      'note' => 'is_string',
      'replaces' => static::conditionalSchema([
        'name' => 'is_string',
      ], $validate_if_universal_is_false),
      'patches' => static::dictionaryOf('is_string'),
      'vetted' => 'is_bool',
    ], $defaults);
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
    $package_name = $validated['package'];
    $version_constraint = $validated['constraint'];
    $install = $validated['install'];
    $patches = $validated['patches'];
    $vetted = $validated['vetted'];
    $note = $validated['note'];
    if ($validated['universal']) {
      return new UniversalRecommendation($package_name, $version_constraint, $install, $vetted, $note, $patches);
    }
    return new static(Closure::fromCallable(function (ExtensionInterface $extension) use ($validated) : bool {
      return $extension->getName() === $validated['replaces']['name'];
    }), $package_name, $version_constraint, $install, $vetted, $note, $patches);
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
   * {@inheritDoc}
   */
  public function getPackageName() : string {
    return $this->packageName;
  }

  /**
   * {@inheritDoc}
   */
  public function getVersionConstraint() : string {
    return $this->versionConstraint;
  }

  /**
   * {@inheritDoc}
   */
  public function hasModulesToInstall() : bool {
    return !empty($this->install);
  }

  /**
   * {@inheritDoc}
   */
  public function getModulesToInstall() : array {
    return $this->install;
  }

  /**
   * {@inheritDoc}
   */
  public function isVetted() : bool {
    return $this->vetted;
  }

  /**
   * {@inheritDoc}
   */
  public function hasPatches() : bool {
    return !empty($this->patches);
  }

  /**
   * {@inheritDoc}
   */
  public function getPatches(): array {
    return $this->patches;
  }

  public function normalize(): array {
    $normalized = [
      'type' => 'packageRecommendation',
      'id' => "{$this->packageName}:{$this->versionConstraint}",
      'attributes' => [
        'requirePackage' => [
          'name' => $this->packageName,
          'versionConstraint' => $this->versionConstraint
        ],
        'installModules' => $this->install,
        'vetted' => $this->vetted,
      ],
    ];

    if (!empty($this->note) && $this->note !== static::NOTE_PLACEHOLDER_STRING) {
      $normalized['attributes']['note'] = $this->note;
    }

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

    $links = array_reduce(array_keys($this->patches), function (array $links, string $patch_description) {
      $links['patch-file--' . md5($patch_description)] = [
        'href' => $this->patches[$patch_description],
        'rel' => 'https://github.com/acquia/acquia_migrate#link-rel-patch-file',
        'title' => $patch_description,
      ];
      return $links;
    }, []);
    if (!empty($links)) {
      $normalized['links'] = $links;
    }

    return $normalized;
  }

}
