<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiListCommand;
use Acquia\Cli\Command\Api\ApiListCommandBase;
use Acquia\Cli\Command\Self\ListCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property ApiListCommandBase $command
 */
class ApiListCommandTest extends CommandTestBase {

  public function setUp(): void {
    parent::setUp();
    $this->application->addCommands($this->getApiCommands());
  }

  protected function createCommand(): Command {
    return $this->injectCommand(ApiListCommand::class);
  }

  public function testApiListCommand(): void {
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString(' api:accounts:ssh-keys-list', $output);
  }

  public function testApiNamespaceListCommand(): void {
    $this->command = $this->injectCommand(ApiListCommandBase::class);
    $name = 'api:accounts';
    $this->command->setName($name);
    $this->command->setNamespace($name);
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString('api:accounts:', $output);
    $this->assertStringContainsString('api:accounts:ssh-keys-list', $output);
    $this->assertStringNotContainsString('api:subscriptions', $output);
  }

  public function testListCommand(): void {
    $this->command = new ListCommand();
    $this->executeCommand();
    $output = $this->getDisplay();
    $this->assertStringContainsString(' api:accounts', $output);
    $this->assertStringNotContainsString(' api:accounts:ssh-keys-list', $output);
  }

}
