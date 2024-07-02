<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\Recommendation\AbandonmentRecommendation;
use Acquia\Cli\Command\App\From\Recommendation\DefinedRecommendation;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Acquia\Cli\Command\App\From\Recommendation\AbandonmentRecommendation
 */
class AbandonmentRecommendationTest extends TestCase
{
    private AbandonmentRecommendation $sut;

    protected function setUp(): void
    {
        parent::setUp();
        // @see \Acquia\Cli\Tests\Commands\App\From\DefinedRecommendationTest::getTestConfigurations()
        // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
        $this->sut = DefinedRecommendation::createFromDefinition([
        'package' => null,
        'note' => 'Example: The module bar is no longer required because its functionality has been incorporated into Drupal core.',
        'replaces' => [
        'name' => 'bar',
        ],
        'vetted' => true,
        ]);
        // phpcs:enable
    }

    /**
     * @covers ::getPackageName
     */
    public function testPackageName(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->getPackageName();
    }

    /**
     * @covers ::getVersionConstraint
     */
    public function testVersionConstraint(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->getVersionConstraint();
    }

    /**
     * @covers ::hasModulesToInstall
     */
    public function testHasModulesToInstall(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->hasModulesToInstall();
    }

    /**
     * @covers ::getModulesToInstall
     */
    public function testGetModulesToInstall(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->getModulesToInstall();
    }

    /**
     * @covers ::hasPatches
     */
    public function testHasPatches(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->hasPatches();
    }

    /**
     * @covers ::isVetted
     */
    public function testIsVetted(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->isVetted();
    }

    /**
     * @covers ::getPatches
     */
    public function testGetPatches(): void
    {
        $this->expectException(\LogicException::class);
        $this->sut->getPatches();
    }
}
