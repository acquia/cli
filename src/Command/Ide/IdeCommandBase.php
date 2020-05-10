<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
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
   * @return \AcquiaCloudApi\Response\IdeResponse|null
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptIdeChoice(
    $question_text,
    Ides $ides_resource
  ): ?IdeResponse {
    $cloud_application_uuid = $this->determineCloudApplication();
    $ides = iterator_to_array($ides_resource->getAll($cloud_application_uuid));
    return $this->promptChooseFromObjects($ides, 'uuid', 'label', $question_text);
  }

}
