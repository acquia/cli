<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 */
abstract class AcsfCommandTestBase extends CommandTestBase
{
    // Test URLs with hyphens to ensure they don't get normalized to underscores.
    protected string $acsfCurrentFactoryUrl = 'https://www.test-something.com';

    protected string $acsfActiveUser = 'tester';

    protected string $acsfUsername = 'tester';

    protected string $acsfKey = 'h@x0r';

    protected string $apiCommandPrefix = 'acsf';

    protected string $apiSpecFixtureFilePath = __DIR__ . '/../../../../../assets/acsf-spec.json';

    /**
     * @return array<mixed>
     */
    protected function getAcsfCredentialsFileContents(): array
    {
        return [
        'acsf_active_factory' => $this->acsfCurrentFactoryUrl,
        'acsf_factories' => [
        $this->acsfCurrentFactoryUrl => [
        'active_user' => $this->acsfActiveUser,
        'url' => $this->acsfCurrentFactoryUrl,
        'users' => [
        $this->acsfUsername => [
        'key' => $this->acsfKey,
        'username' => $this->acsfUsername,
        ],
        ],
        ],
        ],
        DataStoreContract::SEND_TELEMETRY => false,
        ];
    }
}
