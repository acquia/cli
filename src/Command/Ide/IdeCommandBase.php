<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;

/**
 * Class IdeCommandBase.
 */
abstract class IdeCommandBase extends CommandBase {

  /**
   * @param string $question_text
   * @param \AcquiaCloudApi\Endpoints\Ides $ides_resource
   *
   * @param $cloud_application_uuid
   *
   * @return \AcquiaCloudApi\Response\IdeResponse
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptIdeChoice(
    $question_text,
    Ides $ides_resource,
    $cloud_application_uuid
  ): ?IdeResponse {
    $ides = iterator_to_array($ides_resource->getAll($cloud_application_uuid));
    if (empty($ides)) {
      throw new AcquiaCliException('No IDEs exist for this application.');
    }
    /** @var IdeResponse $ide_response */
    $ide_response = $this->promptChooseFromObjects($ides, 'uuid', 'label', $question_text);
    return $ide_response;
  }

  /**
   * Restart PHP inside IDE.
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function restartPhp(): void {
    $this->logger->notice('Restarting PHP...');
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'restart',
      'php-fpm',
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to restart PHP in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

}
