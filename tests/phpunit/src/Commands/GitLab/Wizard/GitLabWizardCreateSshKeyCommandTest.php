<?php

namespace Acquia\Cli\Tests\Commands\GitLab\Wizard;

use Acquia\Cli\Command\GitLab\Wizard\GitLabWizardCreateSshKeyCommand;
use Acquia\Cli\Tests\Commands\WizardTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class GitLabWizardCreateSshKeyCommandTest.
 *
 * @property \Acquia\Cli\Command\GitLab\Wizard\GitLabWizardCreateSshKeyCommand $command
 * @package Acquia\Cli\Tests\GitLab
 * @requires OS linux|darwin
 */
class GitLabWizardCreateSshKeyCommandTest extends WizardTestBase {

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->mockApplicationRequest();
    $this->mockListSshKeysRequest();
    putenv('GITLAB_CI=true');
    putenv('ACQUIA_APPLICATION_UUID=' . self::$application_uuid);
  }

  public function tearDown(): void {
    parent::tearDown();
    putenv('GITLAB_CI');
    putenv('ACQUIA_APPLICATION_UUID');
  }

  /**
   * @return \Acquia\Cli\Command\GitLab\Wizard\GitLabWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(GitLabWizardCreateSshKeyCommand::class);
  }

  public function testCreate(): void {
    parent::testCreate();
  }

  public function testSshKeyAlreadyUploaded(): void {
    parent::testSshKeyAlreadyUploaded();
  }

}
