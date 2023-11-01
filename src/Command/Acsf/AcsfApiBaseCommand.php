<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Command\Api\ApiBaseCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(name: 'acsf:base')]
class AcsfApiBaseCommand extends ApiBaseCommand {

  protected function checkAuthentication(): void {
    if ($this->commandRequiresAuthentication() && !$this->cloudApiClientService->isMachineAuthenticated()) {
      throw new AcquiaCliException('This machine is not yet authenticated with the Acquia Cloud Site Factory. Run `acli auth:acsf-login`');
    }
  }

  /**
   * @todo Remove this method when CLI-791 is resolved.
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
