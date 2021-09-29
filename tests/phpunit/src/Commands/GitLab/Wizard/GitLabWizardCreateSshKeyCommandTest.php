<?php

namespace Acquia\Cli\Tests\Commands\GitLab\Wizard;

use Acquia\Cli\Command\GitLab\Wizard\GitLabWizardCreateSshKeyCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\WizardTestBase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class GitLabWizardCreateSshKeyCommandTest.
 *
 * @property \Acquia\Cli\Command\GitLab\Wizard\GitLabWizardCreateSshKeyCommand $command
 * @package Acquia\Cli\Tests\GitLab
 * @requires OS linux|darwin
 */
class GitLabWizardCreateSshKeyCommandTest extends WizardTestBase {

  /**
   * @var string
   */
  public static $application_uuid = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';

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
