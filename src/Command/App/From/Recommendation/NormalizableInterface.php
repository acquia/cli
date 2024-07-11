<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Recommendation;

/**
 * Interface for a normalizable object.
 */
interface NormalizableInterface
{
    /**
     * Normalizes an object into a single- or multi-dimensional array of
     * scalars.
     *
     * @return array<mixed>
     */
    public function normalize(): array;
}
