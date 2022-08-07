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
    $output_record[] = ['Package Name', 'Package Type', 'Current Version', 'Latest Version', 'Update Type'];
    $output_record[] = ['zen', 'theme', '7.x-3.2', '7.x-6.4', 'Bug fixes' ];
    foreach ($output_record as $output_data) {
      foreach ($output_data as $value) {
        $this->assertStringContainsString($value, $output);
      }
    }
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
    $package_available_updates = $drupalOrgClient->getSecurityRelease('google_analytics', '7.x-2.0');
    $this->assertIsArray($package_available_updates);

    $package_available_updates = $drupalOrgClient->getSecurityRelease('acquia_connector', '7.x-2.15');
    $this->assertIsArray($package_available_updates);
    $this->assertArrayHasKey('acquia_connector', $package_available_updates);
    $this->assertArrayHasKey('package_type', $package_available_updates['acquia_connector']);
    $this->assertStringNotContainsString('project_', $package_available_updates['acquia_connector']['package_type']);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessageMatches("/^Failed to get 'test_package' package latest release data.No release history was found for the requested project/");
    $drupalOrgClient->getSecurityRelease('test_package', '7.x-3.28');

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
