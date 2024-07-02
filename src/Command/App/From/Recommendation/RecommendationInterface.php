<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Recommendation;

use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;

/**
 * Interface for package recommendations.
 */
interface RecommendationInterface
{
    /**
     * Whether this recommendation applies to the given extension.
     *
     * @param \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface $extension
     *   The extension to evaluate.
     * @return bool
     *   TRUE if the recommendation applies, FALSE otherwise.
     */
    public function applies(ExtensionInterface $extension): bool;

    /**
     * The recommended composer package name to replace the applicable extension.
     *
     * @return string
     *   The recommended package's name.
     */
    public function getPackageName(): string;

    /**
     * The recommended version constraint to replace the applicable extension.
     *
     * @return string
     *   The recommended version constraint for the recommended package.
     */
    public function getVersionConstraint(): string;

    /**
     * Whether this recommendation contains modules to install.
     *
     * @return bool
     *   TRUE if the recommendation includes modules to install, FALSE otherwise.
     */
    public function hasModulesToInstall(): bool;

    /**
     * Gets a list of recommended modules to install.
     *
     * This will not always be simply a list containing the package name. Some
     * packages do not need to be installed as modules and some packages have
     * submodules that should be installed also.
     *
     * @return string[]
     *   A list of module names that should be installed.
     */
    public function getModulesToInstall(): array;

    /**
     * Whether the recommendation is vetted or not.
     */
    public function isVetted(): bool;

    /**
     * Whether the recommendation contains patches or not.
     *
     * @return bool
     *   TRUE if the recommendation contains patches; FALSE otherwise.
     */
    public function hasPatches(): bool;

    /**
     * Gets an array of recommended patches for the recommended package.
     *
     * @return array<mixed>
     *   An associative array whose keys are a description of the patch's contents
     *   and whose values are URLs or relative paths to a patch file.
     */
    public function getPatches(): array;
}
