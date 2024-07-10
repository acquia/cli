<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide;

trait IdeRequiredTestTrait
{
    /**
     * This method is called before each test.
     */
    public function setUp(): void
    {
        parent::setUp();
        IdeHelper::setCloudIdeEnvVars();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        IdeHelper::unsetCloudIdeEnvVars();
    }
}
