<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\HelloWorldCommand;
use Acquia\Cli\Tests\CommandTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Command\Command;

class UpdateCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(HelloWorldCommand::class);
  }

  public function testSelfUpdate(): void {
    $this->setUpdateClient();
    $this->application->setVersion('2.8.4');
    $this->executeCommand();
    self::assertEquals(0, $this->getStatusCode());
    self::assertStringContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
  }

  public function testBadResponseFailsSilently(): void {
    $this->setUpdateClient(403);
    $this->application->setVersion('2.8.4');
    $this->executeCommand();
    self::assertEquals(0, $this->getStatusCode());
    self::assertStringNotContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
  }

  public function testNetworkErrorFailsSilently(): void {
    $guzzleClient = $this->prophet->prophesize(Client::class);
    $guzzleClient->get('https://api.github.com/repos/acquia/cli/releases')
      ->willThrow(RequestException::class);
    $this->command->setUpdateClient($guzzleClient->reveal());
    $this->application->setVersion('2.8.4.9999s');
    $this->executeCommand();
    self::assertEquals(0, $this->getStatusCode());
    self::assertStringNotContainsString('Acquia CLI 2.8.5 is available', $this->getDisplay());
  }

}
