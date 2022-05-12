<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;

/**
 * Class CommandBase.
 *
 */
class AcsfApiBaseCommand extends ApiBaseCommand {
  protected static $defaultName = 'acsf:base';

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function checkAuthentication(): void {
    if ($this->commandRequiresAuthentication($this->input) && !$this->cloudApiClientService->isMachineAuthenticated($this->datastoreCloud)) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Acquia Cloud Site Factory. Please run `acli auth:acsf-login`');
    }
  }

}
