<?php

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * Class ApiCommandTest.
 *
 * @property \Acquia\Cli\Command\Api\ApiBaseCommand $command
 * @package Acquia\Cli\Tests\Api
 */
abstract class AcsfCommandTestBase extends CommandTestBase {

  // Test URLs with hyphens to ensure they don't get normalized to underscores.
  protected string $acsfCurrentFactoryUrl = 'https://www.test-something.com';

  protected string $acsfActiveUser = 'tester';

  protected string $acsfUsername = 'tester';

  protected string $acsfKey = 'h@x0r';

  /**
   * @return array
   */
  protected function getAcsfCredentialsFileContents(): array {
    return [
      'acsf_active_factory' => $this->acsfCurrentFactoryUrl,
      'acsf_factories' => [
        $this->acsfCurrentFactoryUrl => [
          'url' => $this->acsfCurrentFactoryUrl,
          'active_user' => $this->acsfActiveUser,
          'users' => [
            $this->acsfUsername => [
              'username' => $this->acsfUsername,
              'key' => $this->acsfKey,
            ],
          ],
        ],
      ],
      DataStoreContract::SEND_TELEMETRY => FALSE,
    ];
  }

}
