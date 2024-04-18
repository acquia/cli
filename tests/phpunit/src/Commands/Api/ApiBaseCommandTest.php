<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Api;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;

class ApiBaseCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(ApiBaseCommand::class);
  }

  public function testApiBaseCommand(): void {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('api:base is not a valid command');
    $this->executeCommand();
  }

}
