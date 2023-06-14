<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;

/**
 * Mock recommendation object.
 */
final class TestRecommendation implements RecommendationInterface {

  public const ABANDON = '%ABANDON%';

  protected string $packageName;

  public function __construct(
    protected bool $shouldApply,
    ?string $package_name,
    protected string $versionConstraint = 'n/a',
    protected array $install = [],
    protected bool $vetted = FALSE,
    protected array $patches = []
  ) {
    assert(!is_null($package_name) || $versionConstraint === 'n/a');
    $this->packageName = $package_name ?: self::ABANDON;
  }

  public function applies(ExtensionInterface $extension): bool {
    return $this->shouldApply;
  }

  public function getPackageName(): string {
    return $this->packageName;
  }

  public function getVersionConstraint(): string {
    return $this->versionConstraint;
  }

  public function hasModulesToInstall(): bool {
    return !empty($this->install);
  }

  /**
   * {@inheritDoc}
   */
  public function getModulesToInstall(): array {
    return $this->install;
  }

  public function isVetted(): bool {
    return $this->vetted;
  }

  public function hasPatches(): bool {
    return !empty($this->patches);
  }

  /**
   * {@inheritDoc}
   */
  public function getPatches(): array {
    return $this->patches;
  }

}
