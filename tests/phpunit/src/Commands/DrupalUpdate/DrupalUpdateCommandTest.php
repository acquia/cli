<?php

namespace Acquia\Cli\Tests\Commands\DrupalUpdate;

use Acquia\Cli\Command\DrupalUpdate\DrupalOrgClient;
use Acquia\Cli\Command\DrupalUpdate\DrupalUpdateCommand;
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
    $this->expectExceptionMessage('No Package Info files found.');
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-invalid-project');
    $args = ['--drupal-root-path' => $this->fixtureDir . '/drupal7-invalid-project'];
    $this->executeCommand($args);
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7ProjectNotFound() {
    // Set properties and execute.
    $args = ['--drupal-root-path' => $this->fixtureDir];
    $this->executeCommand($args);
    $this->assertStringContainsString('Could not find a local Drupal project.', $this->getDisplay());
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7UpToDateUpdateCase() {
    $args = ['--drupal-root-path' => $this->fixtureDir . '/drupal7-up-to-date-project'];
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-up-to-date-project');
    $this->executeCommand($args);

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Branch already up to date.', $this->getDisplay());
  }

  /**
   * @throws \Exception
   * @requires OS linux|darwin
   */
  public function testDrupal7SuccefulUpdateCommand() {
    $args = ['--drupal-root-path' => $this->fixtureDir . '/drupal7-valid-project'];
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-valid-project');
    $this->executeCommand($args);

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
  public function testDetermineAvailablePackageReleases() {
    $this->expectException(AcquiaCliException::class);
    $input = $this->getMockBuilder(InputInterface::class)->getMock();
    $output = $this->getMockBuilder(OutputInterface::class)->getMock();
    $drupalOrgClient = new DrupalOrgClient($input, $output);
    $drupalOrgClient->getSecurityRelease('', '7.x-3.28');
  }

}
