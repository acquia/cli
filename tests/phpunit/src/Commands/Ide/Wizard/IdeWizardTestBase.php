<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Tests\CommandTestBase;

/**
 * Class IdeWizardTestBase.
 */
abstract class IdeWizardTestBase extends IdeRequiredTestBase {

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
   *
   * @param null $output
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    $this->getCommandTester();
    $this->application->addCommands([
      $this->injectCommand(SshKeyCreateCommand::class),
      $this->injectCommand(SshKeyDeleteCommand::class),
      $this->injectCommand(SshKeyUploadCommand::class),
    ]);

    parent::setUp();
  }

}
