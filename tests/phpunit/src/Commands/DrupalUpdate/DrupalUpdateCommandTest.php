<?php


namespace Acquia\Cli\Tests\Commands\DrupalUpdate;

use Acquia\Cli\Command\DrupalUpdate\DrupalUpdateCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class DrupalUpdateCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(DrupalUpdateCommand::class);
  }

  //    /**
  //     * @return array
  //     */
  //    public function providerTestCommand(): array {
  //        return [
  //            ['drupal-root-path'=>
  //
  //            ],
  //        ];
  //    }

  /**
   *
   * @param $mocked_gitlab_projects
   * @param $args
   * @param $inputs
   *
   * @throws \Psr\Cache\InvalidArgumentException|\Exception
   */
  public function testDrupal7UpdateCommand() {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Drupal 7 project not found.');
    // Set properties and execute.
    $this->executeCommand();
  }

  /**
   *
   * @param $mocked_gitlab_projects
   * @param $args
   * @param $inputs
   *
   * @throws \Psr\Cache\InvalidArgumentException|\Exception
   */
  public function testDrupal7FailedUpdateCommand() {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Drupal 7 project not found.');
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-invalid-project');
    $args = ['--drupal-root-path' => $this->fixtureDir . '/drupal7-invalid-project'];
    $this->executeCommand($args);
    $output = $this->getDisplay();
  }

  public function testDrupal7UpToDateUpdateCase() {
    $args = ['--drupal-root-path' => $this->fixtureDir . '/drupal7-up-to-date-project'];
    // Set properties and execute.
    $this->command->setRepoRoot($this->fixtureDir . '/drupal7-up-to-date-project');
    $this->executeCommand($args);

    // Assertions.
    $this->assertEquals(0, $this->getStatusCode());
    $output = $this->getDisplay();
    $this->assertStringContainsString('Branch already up to date.', $output);
  }

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
    $this->assertStringContainsString('services', $output);
    $this->assertStringContainsString('webform', $output);
  }

}
