<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 */
abstract class AcsfCommandTestBase extends CommandTestBase
{
    // Test URLs with hyphens to ensure they don't get normalized to underscores.
    protected static string $acsfCurrentFactoryUrl = 'https://www.test-something.com';

    protected static string $acsfActiveUser = 'tester';

    protected static string $acsfUsername = 'tester';

    protected static string $acsfKey = 'abcdefghijklmnop';

    protected string $apiCommandPrefix = 'acsf';

    protected static string $apiSpecFixtureFilePath = __DIR__ . '/../../../../../assets/acsf-spec.json';

    /**
     * @return array<mixed>
     */
    protected function getApiCommands(): array
    {
        $apiCommandHelper = new ApiCommandHelper($this->logger);
        $commandFactory = $this->getCommandFactory();
        return $apiCommandHelper->getApiCommands(self::$apiSpecFixtureFilePath, $this->apiCommandPrefix, $commandFactory);
    }

    /**
     * @return array<mixed>
     */
    protected static function getAcsfCredentialsFileContents(): array
    {
        return [
            'acsf_active_factory' => self::$acsfCurrentFactoryUrl,
            'acsf_factories' => [
                self::$acsfCurrentFactoryUrl => [
                    'active_user' => self::$acsfActiveUser,
                    'url' => self::$acsfCurrentFactoryUrl,
                    'users' => [
                        self::$acsfUsername => [
                            'key' => self::$acsfKey,
                            'username' => self::$acsfUsername,
                        ],
                    ],
                ],
            ],
            DataStoreContract::SEND_TELEMETRY => false,
        ];
    }
}
