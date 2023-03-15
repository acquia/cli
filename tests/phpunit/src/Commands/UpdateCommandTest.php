<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\HelloWorldCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class UpdateCommandTest.
 *
 * @package Acquia\Cli\Tests\Commands
 * @property \Acquia\Cli\Command\HelloWorldCommand $command
 */
class UpdateCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(HelloWorldCommand::class);
  }

  public function testSelfUpdate(): void {
    $this->setUpdateClient();
    $this->application->setVersion('2.8.4');
    $this->executeCommand([], []);
    self::assertEquals(0, $this->getStatusCode());
    self::assertStringContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
  }

  public function testSilentFailure(): void {
    $this->setUpdateClient(403);
    $this->application->setVersion('2.8.4');
    $this->executeCommand([], []);
    self::assertEquals(0, $this->getStatusCode());
    self::assertStringNotContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
  }

}
