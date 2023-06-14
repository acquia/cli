<?php

declare(strict_types=1);

namespace AcquiaMigrate;

/**
 * Interface for a normalizable object.
 */
interface NormalizableInterface {

  /**
   * Normalizes an object into a single- or multi-dimensional array of scalars.
   */
  public function normalize(): array;

}
