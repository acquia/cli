<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\CloudApi\ClientService;

/**
 * Extends rather than replaces the base authentication check, as
 * AcsfClientService does.
 */
class TestOverridingClientService extends ClientService
{
    public bool $overrideWasCalled = false;

    protected function checkAuthentication(): bool
    {
        $this->overrideWasCalled = true;
        return parent::checkAuthentication();
    }
}
