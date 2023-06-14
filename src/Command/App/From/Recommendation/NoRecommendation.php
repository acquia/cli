<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App\From\Recommendation;

use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use LogicException;

/**
 * Object to be returned when a replacement recommendation cannot be determined.
 */
final class NoRecommendation implements RecommendationInterface {

  public function applies(ExtensionInterface $extension): bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  public function getPackageName(): string {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a % class instance.', __FUNCTION__, __CLASS__));
  }

  public function getVersionConstraint(): string {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  public function hasModulesToInstall(): bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function getModulesToInstall(): array {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  public function isVetted(): bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  public function hasPatches(): bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

  /**
   * {@inheritdoc}
   */
  public function getPatches(): array {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

}
