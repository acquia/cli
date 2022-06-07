<?php

namespace Acquia\Cli\Tests\Commands\Acsf;

use Acquia\Cli\Command\Acsf\AcsfListCommand;
use Acquia\Cli\Command\Acsf\AcsfListCommandBase;
use Acquia\Cli\Command\Self\ListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class AcsfListCommandTest extends CommandTestBase {

  protected $apiSpecFixtureFilePath = __DIR__ . '/../../../../../assets/acsf-spec.yaml';
  protected string $apiCommandPrefix = 'acsf';

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->application->addCommands($this->getApiCommands());
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AcsfListCommand::class);
  }

  /**
   * Tests the 'acsf:list' command.
   *
   * @throws \Exception
   */
  public function testAcsfListCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('acsf:api', $output);
    $this->assertStringContainsString('acsf:api:ping', $output);
    $this->assertStringContainsString('acsf:info:audit-events-find', $output);
  }

  /**
   * Tests the 'acsf:*' list commands.
   *
   * @throws \Exception
   */
  public function testApiNamespaceListCommand(): void {
    $this->command = $this->injectCommand(AcsfListCommandBase::class);
    $name = 'acsf:api';
    $this->command->setName($name);
    $this->command->setNamespace($name);
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('acsf:api:ping', $output);
    $this->assertStringNotContainsString('acsf:groups', $output);
  }

  /**
   * Tests the 'list' command.
   *
   * @throws \Exception
   */
  public function testListCommand(): void {
    $this->command = new ListCommand('list');
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('acsf:api', $output);
    $this->assertStringNotContainsString('acsf:api:ping', $output);
  }

}
