<?php

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Input\InputInterface;

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
    if ($this->commandRequiresAuthentication() && !$this->cloudApiClientService->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Acquia Cloud Site Factory. Run `acli auth:acsf-login`');
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @todo Remove this method when CLI-791 is resolved.
   *
   * @return string
   */
  protected function getRequestPath(InputInterface $input): string {
    $path = $this->path;

    $arguments = $input->getArguments();
    // The command itself is the first argument. Remove it.
    array_shift($arguments);
    foreach ($arguments as $key => $value) {
      $token = '%' . $key;
      if (str_contains($path, $token)) {
        return str_replace($token, $value, $path);
      }
    }

    return parent::getRequestPath($input);
  }

}
