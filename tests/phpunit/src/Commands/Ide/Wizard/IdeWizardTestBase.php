<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyUploadCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestBase;

/**
 * Class IdeWizardTestBase.
 */
abstract class IdeWizardTestBase extends IdeRequiredTestBase {

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   */
  public function setUp($output = NULL): void {
    parent::setUp();
    $this->getCommandTester();
    $this->application->addCommands([
      $this->injectCommand(SshKeyCreateCommand::class),
      $this->injectCommand(SshKeyDeleteCommand::class),
      $this->injectCommand(SshKeyUploadCommand::class),
    ]);
  }

}
