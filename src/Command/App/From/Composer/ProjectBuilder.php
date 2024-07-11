<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Composer;

use Acquia\Cli\Command\App\From\Configuration;
use Acquia\Cli\Command\App\From\Recommendation\AbandonmentRecommendation;
use Acquia\Cli\Command\App\From\Recommendation\NormalizableInterface;
use Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface;
use Acquia\Cli\Command\App\From\Recommendation\Resolver;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface;

/**
 * Builds a new Drupal 9 project definition.
 */
final class ProjectBuilder
{
    /**
     * The current configuration.
     */
    protected Configuration $configuration;

    /**
     * The recommendation resolver.
     */
    protected Resolver $resolver;

    /**
     * The site inspector.
     */
    protected SiteInspectorInterface $siteInspector;

    /**
     * ProjectBuilder constructor.
     *
     * @param \Acquia\Cli\Command\App\From\Configuration $configuration
     *   A configuration object.
     * @param \Acquia\Cli\Command\App\From\Recommendation\Resolver $recommendation_resolver
     *   A recommendation resolver.
     * @param \Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface $site_inspector
     *   A site inspector.
     */
    public function __construct(Configuration $configuration, Resolver $recommendation_resolver, SiteInspectorInterface $site_inspector)
    {
        $this->configuration = $configuration;
        $this->resolver = $recommendation_resolver;
        $this->siteInspector = $site_inspector;
    }

    /**
     * Gets an array representing a D9+ composer.json file for the current site.
     *
     * @return array<mixed>
     *   An array that can be encoded as JSON and written to a file. Calling
     *   `composer install` in the same directory as that file should yield a new
     *   Drupal project with Drupal 9+ installed, in addition to the Acquia
     *   Migrate module, and some of all of the D9 replacements for the current
     *   site's Drupal 7 modules.
     */
    public function buildProject(): array
    {
        $modules_to_install = [];
        $recommendations = [];
        $composer_json = $this->configuration->getRootPackageDefinition();

        // Add recommended dependencies and patches, if any.
        foreach ($this->resolver->getRecommendations() as $recommendation) {
            assert($recommendation instanceof RecommendationInterface);
            if ($recommendation instanceof NormalizableInterface) {
                $recommendations[] = $recommendation->normalize();
            }
            if ($recommendation instanceof AbandonmentRecommendation) {
                continue;
            }
            $recommended_package_name = $recommendation->getPackageName();
            // Special case: to guarantee that a valid and installable `composer.json`
            // file is generated, `$composer_json` is first populated with valid
            // `require`s based on `config.json`:
            // - `drupal/core-composer-scaffold`
            // - `drupal/core-project-message`
            // - `drupal/core-recommended`
            // When `recommendations.json` is unreachable or invalid, the versions
            // specified in `config.json` are what end up in the `composer.json` file.
            // Since without a `recommendations.json` file no patches can be applied,
            // this guarantees a viable Drupal 9-based `composer.json`.
            // However, when a valid `recommendations.json` is available, then that
            // default version that `$composer_json` was initialized with should be
            // overwritten by the recommended `drupal/core` version in
            // `recommendations.json`, because the patches associated with Drupal core
            // may only apply to a particular version.
            // Because the `drupal/core-*` package versions have sensible defaults
            // from `config.json` and are overwritten if and only if a valid
            // `recommendations.json` is available, we can guarantee a viable
            // `composer.json` file.
            if ($recommended_package_name === 'drupal/core') {
                $core_version_constraint = $recommendation->getVersionConstraint();
                if ($core_version_constraint !== '*') {
                    $composer_json['require']['drupal/core-composer-scaffold'] = $core_version_constraint;
                    $composer_json['require']['drupal/core-project-message'] = $core_version_constraint;
                    $composer_json['require']['drupal/core-recommended'] = $core_version_constraint;
                }
            } else {
                $composer_json['require'][$recommended_package_name] = $recommendation->getVersionConstraint();
            }
            if ($recommendation->hasPatches()) {
                $composer_json['extra']['patches'][$recommended_package_name] = $recommendation->getPatches();
            }
            if ($recommendation->isVetted() && $recommendation->hasModulesToInstall()) {
                array_push($modules_to_install, ...$recommendation->getModulesToInstall());
            }
        }

        // Multiple recommendations may ask the same module to get installed.
        $modules_to_install = array_unique($modules_to_install);

        // Sort the dependencies and patches by package name.
        sort($modules_to_install);
        if (isset($composer_json['require'])) {
            ksort($composer_json['require']);
        }
        if (isset($composer_json['extra']['patches'])) {
            ksort($composer_json['extra']['patches']);
        }

        $source_modules = array_values(array_map(function (ExtensionInterface $module) {
          // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
            return [
                'name' => $module->getName(),
                'humanName' => $module->getHumanName(),
                'version' => $module->getVersion(),
            ];
          // phpcs:enable
        }, $this->siteInspector->getExtensions(SiteInspectorInterface::FLAG_EXTENSION_MODULE | SiteInspectorInterface::FLAG_EXTENSION_ENABLED)));
        $module_names = array_column($source_modules, 'name');
        array_multisort($module_names, SORT_STRING, $source_modules);

        $recommendation_ids = array_column($recommendations, 'id');
        array_multisort($recommendation_ids, SORT_STRING, $recommendations);

        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        return [
            'installModules' => $modules_to_install,
            'filePaths' => [
                'public' => $this->siteInspector->getPublicFilePath(),
                'private' => $this->siteInspector->getPrivateFilePath(),
            ],
            'sourceModules' => $source_modules,
            'recommendations' => $recommendations,
            'rootPackageDefinition' => $composer_json,
        ];
        // phpcs:enable
    }
}
