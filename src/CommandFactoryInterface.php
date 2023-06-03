<?php

declare(strict_types = 1);

namespace Acquia\Cli;

use Acquia\Cli\Command\Acsf\AcsfApiBaseCommand;
use Acquia\Cli\Command\Acsf\AcsfListCommand;
use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Command\Api\ApiListCommand;

interface CommandFactoryInterface {

  // @todo return type should really be an interface
  public function createCommand(): ApiBaseCommand|AcsfApiBaseCommand;

  public function createListCommand(): ApiListCommand|AcsfListCommand;

}
