<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Recommendation;

use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface;

/**
 * Resolves Drupal 7 extension information into a Drupal 9+ recommendation.
 */
final class Resolver
{
    /**
     * A Drupal 7 site inspector.
     *
     * @see \Acquia\Cli\Command\App\From\SourceSite\Drupal7SiteInspector
     */
    protected SiteInspectorInterface $inspector;

    /**
     * A list of defined recommendations that apply no matter the context.
     */
    protected Recommendations $universalRecommendations;

    /**
     * A list of defined recommendations that depend on context.
     */
    protected Recommendations $conditionalRecommendations;

    /**
     * Resolver constructor.
     *
     * @param \Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface $inspector
     *   A site inspector.
     * @param \Acquia\Cli\Command\App\From\Recommendation\Recommendations $recommendations
     *   A set of defined recommendations. These are *all possible*
     *   recommendations. It is the resolves job to narrow these down by using the
     *   site inspector to retrieve information about the source site.
     */
    public function __construct(SiteInspectorInterface $inspector, Recommendations $recommendations)
    {
        $this->inspector = $inspector;
        $this->universalRecommendations = new Recommendations([]);
        $this->conditionalRecommendations = new Recommendations([]);
        foreach ($recommendations as $recommendation) {
            if ($recommendation instanceof UniversalRecommendation) {
                $this->universalRecommendations->append($recommendation);
            } else {
                $this->conditionalRecommendations->append($recommendation);
            }
        }
    }

    /**
     * Gets a recommendation for the given extension.
     *
     * @return \Acquia\Cli\Command\App\From\Recommendation\Recommendations
     *   A resolved suite of recommendations.
     */
    public function getRecommendations(): Recommendations
    {
        $enabled_modules = $this->inspector->getExtensions(SiteInspectorInterface::FLAG_EXTENSION_ENABLED | SiteInspectorInterface::FLAG_EXTENSION_MODULE);
        return array_reduce($enabled_modules, function (Recommendations $recommendations, ExtensionInterface $extension) {
            $resolutions = $this->getRecommendationsForExtension($extension);
            foreach ($resolutions as $resolution) {
                if (!$resolution instanceof NoRecommendation) {
                    $recommendations->append($resolution);
                }
            }
            return $recommendations;
        }, $this->universalRecommendations);
    }

    /**
     * Gets a recommendation for the given extension.
     *
     * @param \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface $extension
     *   A Drupal 7 extension for which a package recommendation should be
     *   resolved.
     * @return \Acquia\Cli\Command\App\From\Recommendation\Recommendations
     *   A resolved recommendation.
     */
    protected function getRecommendationsForExtension(ExtensionInterface $extension): Recommendations
    {
        $recommendations = new Recommendations();
        foreach ($this->conditionalRecommendations as $recommendation) {
            if ($recommendation->applies($extension)) {
                $recommendations->append($recommendation);
            }
        }
        return $recommendations;
    }
}
