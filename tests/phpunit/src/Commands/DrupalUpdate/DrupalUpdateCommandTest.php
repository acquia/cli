<?php

namespace Acquia\Cli\Tests\Commands\DrupalUpdate;

use Acquia\Cli\Command\DrupalUpdate\DrupalOrgClient;
use Acquia\Cli\Command\DrupalUpdate\DrupalUpdateCommand;
use Acquia\Cli\Command\DrupalUpdate\FileSystemUtility;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DrupalUpdateCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(DrupalUpdateCommand::class);
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7FailedUpdateCommand() {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Not valid Drupal 7 project.');
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-invalid-project');
    $this->executeCommand();
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7ProjectNotFound() {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Not valid Drupal 7 project.');
    $this->executeCommand();
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7UpToDateUpdateCase() {
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-up-to-date-project');
    $this->executeCommand();

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Branch already up to date.', $this->getDisplay());
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7SuccessfulUpdateCommand() {
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-valid-project');
    $this->executeCommand();

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
    $output = $this->getDisplay();
    $this->assertStringContainsString('acquia_connector', $output);
    $this->assertStringContainsString('drupal', $output);
    $this->assertStringContainsString('webform', $output);
  }

  /**
   *
   * @throws \ReflectionException
   * @requires OS linux|darwin
   */
  public function testFetchAvailablePackageReleases() {

    $input = $this->getMockBuilder(InputInterface::class)->getMock();
    $output = $this->getMockBuilder(OutputInterface::class)->getMock();
    $drupalOrgClient = new DrupalOrgClient($input, $output);
    $package_available_updates = $drupalOrgClient->getSecurityRelease('drupal/google_analytics', '7.x-2.0');
    $this->assertIsArray($package_available_updates);

    $package_available_updates = $drupalOrgClient->getSecurityRelease('acquia/acquia_connector', '7.x-2.15');
    $this->assertIsArray($package_available_updates);

    $this->expectException(AcquiaCliException::class);
    $drupalOrgClient->getSecurityRelease('', '7.x-3.28');

  }

  /**
   * @requires OS linux|darwin
   */
  public function testDetermineD7AppMethod() {
    $non_valid_root_path = FileSystemUtility::determineD7App('');
    $this->assertEquals(0, $non_valid_root_path);

    $valid_root_path = FileSystemUtility::determineD7App($this->fixtureDir . '/drupal7-valid-project');
    $this->assertEquals(1, $valid_root_path);
  }

}
