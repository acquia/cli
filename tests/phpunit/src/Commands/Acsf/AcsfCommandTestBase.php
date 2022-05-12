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

  protected $acsfCurrentFactoryUrl = 'https://www.test.com';

  protected $acsfActiveUser = 'tester';

  protected $acsfUsername = 'tester';

  protected $acsfPassword = 'h@x0r';

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
              'password' => $this->acsfPassword,
            ],
          ],
        ],
      ],
      DataStoreContract::SEND_TELEMETRY => FALSE,
    ];
  }

}
