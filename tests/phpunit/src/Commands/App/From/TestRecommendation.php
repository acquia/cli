<?php declare(strict_types=1);

namespace AcquiaMigrate\Tests;

use AcquiaMigrate\ExtensionInterface;
use AcquiaMigrate\RecommendationInterface;

/**
 * Mock recommendation object.
 */
final class TestRecommendation implements RecommendationInterface {

  public const ABANDON = '%ABANDON%';

  protected $shouldApply;

  protected $packageName;

  protected $versionConstraint;

  protected $install;

  protected $vetted;

  protected $patches;

  public function __construct(bool $should_apply, ?string $package_name, string $version_constraint = 'n/a', array $install = [], bool $vetted = FALSE, array $patches = []) {
    assert(!is_null($package_name) || $version_constraint === 'n/a');
    $this->shouldApply = $should_apply;
    $this->packageName = $package_name ?: self::ABANDON;
    $this->versionConstraint = $version_constraint;
    $this->install = $install;
    $this->vetted = $vetted;
    $this->patches = $patches;
  }

  /**
   * {@inheritDoc}
   */
  public function applies(ExtensionInterface $extension) : bool {
    return $this->shouldApply;
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
  public function getPatches() : array {
    return $this->patches;
  }

}
