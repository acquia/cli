<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\IdeCommandTrait;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;

abstract class IdeCommandBase extends CommandBase {

  use IdeCommandTrait;

  private string $xdebugIniFilepath = '/home/ide/configs/php/xdebug.ini';

  /**
   * @param $cloud_application_uuid
   */
  protected function promptIdeChoice(
    string $question_text,
    Ides $ides_resource,
    $cloud_application_uuid
  ): ?IdeResponse {
    $ides = iterator_to_array($ides_resource->getAll($cloud_application_uuid));
    if (empty($ides)) {
      throw new AcquiaCliException('No IDEs exist for this application.');
    }

    $choices = [];
    foreach ($ides as $ide) {
      $choices[] = "$ide->label ($ide->uuid)";
    }
    $choice = $this->io->choice($question_text, $choices, $choices[0]);
    $chosen_environment_index = array_search($choice, $choices, TRUE);

    return $ides[$chosen_environment_index];
  }

  /**
   * Start service inside IDE.
   */
  protected function startService(string $service): void {
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'start',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to start ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

  /**
   * Stop service inside IDE.
   */
  protected function stopService(string $service): void {
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'stop',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to stop ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

  /**
   * Restart service inside IDE.
   */
  protected function restartService(string $service): void {
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'restart',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to restart ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

  public function setXdebugIniFilepath(string $file_path): void {
    $this->xdebugIniFilepath = $file_path;
  }

  protected function getXdebugIniFilePath(): string {
    return $this->xdebugIniFilepath;
  }

}
