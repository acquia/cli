<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * Class IdeWizardTestBase.
 */
abstract class IdeWizardTestBase extends CommandTestBase {

  /**
   * @var string
   */
  protected $remote_ide_uuid;

  /**
   * @var string
   */
  protected $application_uuid;

  /**
   * This method is called before each test.
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setCommand($this->createCommand());
    $this->getCommandTester();
    $this->application->addCommands([
      new SshKeyCreateCommand(),
      new SshKeyDeleteCommand(),
      new SshKeyUploadCommand(),
    ]);

    $this->remote_ide_uuid = '4ba6c569-5084-4b6d-9467-019ccb5dc847';
    $this->application_uuid = '257a5440-22c3-49d1-894d-29497a1cf3b8';

    putenv('REMOTEIDE_UUID=' . $this->remote_ide_uuid);
    putenv('ACQUIA_APPLICATION_UUID=' . $this->application_uuid);
    putenv('ACQUIA_USER_UUID=4acf8956-45df-3cf4-5106-065b62cf1ac8');
    putenv('AH_SITE_ENVIRONMENT=IDE');
  }

}
