<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'acsf:base')]
class AcsfApiBaseCommand extends ApiBaseCommand {

  protected function checkAuthentication(): void {
    if ((new \ReflectionClass(static::class))->getAttributes(RequireAuth::class) && !$this->cloudApiClientService->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Acquia Cloud Site Factory. Run `acli auth:acsf-login`');
    }
  }

}
