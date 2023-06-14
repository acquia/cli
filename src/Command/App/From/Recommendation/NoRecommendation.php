<?php

declare(strict_types=1);

namespace AcquiaMigrate\Recommendation;

use AcquiaMigrate\ExtensionInterface;
use AcquiaMigrate\RecommendationInterface;
use LogicException;

/**
 * Object to be returned when a replacement recommendation cannot be determined.
 */
final class NoRecommendation implements RecommendationInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(ExtensionInterface $extension) : bool {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
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
  public function isVetted() : bool {
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
  public function getPatches() : array {
    throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
  }

}
