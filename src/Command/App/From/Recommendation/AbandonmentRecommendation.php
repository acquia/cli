<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\Recommendation;

use Acquia\Cli\Command\App\From\Safety\ArrayValidationTrait;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use Closure;
use Exception;
use LogicException;

/**
 * Represents a recommendation *not* to require a package.
 */
class AbandonmentRecommendation implements RecommendationInterface, NormalizableInterface
{
    use ArrayValidationTrait;

    /**
     * An anonymous function that determines if this recommendation is applicable.
     */
    protected \Closure $evaluateExtension;

    /**
     * The original decoded definition.
     *
     * @var array<mixed>
     */
    protected array $definition;

    /**
     * An array of extensions to which this recommendation applied.
     *
     * @var \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface[]
     */
    protected array $appliedTo = [];

    /**
     * AbandonmentRecommendation constructor.
     *
     * @param \Closure $extension_evaluator
     *   An anonymous function that determines if this recommendation applies to
     *   an extension.
     * @param array $definition
     *   The original decoded definition.
     */
    protected function __construct(Closure $extension_evaluator, array $definition)
    {
        $this->evaluateExtension = $extension_evaluator;
        $this->definition = $definition;
    }

    /**
     * Creates a new recommendation.
     *
     * @param mixed $definition
     *   A static recommendation definition. This must be an array. However, other
     *   value types are accepted because this method performs validation on the
     *   given value.
     * @return \Acquia\Cli\Command\App\From\Recommendation\RecommendationInterface
     *   A new AbandonmentRecommendation object if the given definition is valid or
     *   a new NoRecommendation object otherwise.
     */
    public static function createFromDefinition(mixed $definition): RecommendationInterface
    {
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        $validator = static::schema([
            'package' => 'is_null',
            'note' => 'is_string',
            'replaces' => static::schema([
                'name' => 'is_string',
            ]),
            'vetted' => 'is_bool',
        ]);
        // phpcs:enable
        try {
            $validated = $validator($definition);
        } catch (Exception $e) {
            // Under any circumstance where the given recommendation configuration is
            // invalid, we still want the rest of the script to proceed. I.e. it's
            // better to produce a valid composer.json with a missing recommendation
            // than to fail to create one at all.
            return new NoRecommendation();
        }
        return new AbandonmentRecommendation(Closure::fromCallable(function (ExtensionInterface $extension) use ($validated): bool {
            return $extension->getName() === $validated['replaces']['name'];
        }), $validated);
    }

    public function applies(ExtensionInterface $extension): bool
    {
        if (($this->evaluateExtension)($extension)) {
            array_push($this->appliedTo, $extension);
            return true;
        }
        return false;
    }

    public function getPackageName(): string
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a % class instance.', __FUNCTION__, __CLASS__));
    }

    public function getVersionConstraint(): string
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
    }

    public function hasModulesToInstall(): bool
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function getModulesToInstall(): array
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
    }

    public function hasPatches(): bool
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
    }

    public function isVetted(): bool
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function getPatches(): array
    {
        throw new LogicException(sprintf('It is nonsensical to call the %s() method on a %s class instance.', __FUNCTION__, __CLASS__));
    }

    /**
     * {@inheritDoc}
     */
    public function normalize(): array
    {
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        $normalized = [
            'type' => 'abandonmentRecommendation',
            'id' => "abandon:{$this->definition['replaces']['name']}",
            'attributes' => [
                'note' => $this->definition['note'],
            ],
        ];

        $recommended_for = [
            'data' => array_map(function (ExtensionInterface $extension) {
                return [
                    'type' => $extension->isModule() ? 'module' : 'theme',
                    'id' => $extension->getName(),
                ];
            }, $this->appliedTo),
        ];
        // phpcs:enable
        if (!empty($recommended_for['data'])) {
            $normalized['relationships']['recommendedFor'] = $recommended_for;
        }

        return $normalized;
    }
}
