<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;

abstract class PushCommandBase extends CommandBase {

  protected Checklist $checklist;

}
