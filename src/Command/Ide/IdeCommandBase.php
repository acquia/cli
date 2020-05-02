<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class IdeCommandBase.
 */
abstract class IdeCommandBase extends CommandBase {

  /**
   * @param string $question_text
   * @param \AcquiaCloudApi\Endpoints\Ides $ides_resource
   *
   * @return \AcquiaCloudApi\Response\IdeResponse|null
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
