<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class IdeCommandBase
 */
abstract class IdeCommandBase extends CommandBase
{
    use ExecTrait;

    /**
     * @param string $question_text
     * @param \AcquiaCloudApi\Endpoints\Ides $ides_resource
     *
     * @return false|int|string
     */
    protected function promptIdeChoice(
        $question_text,
        Ides $ides_resource
    ) {
        $cloud_application_uuid = $this->determineCloudApplication();
        $ides_list = [];
        foreach ($ides_resource->getAll($cloud_application_uuid) as $ide) {
            $ides_list[$ide->uuid] = $ide->label;
        }
        $ide_labels = array_values($ides_list);
        $question = new ChoiceQuestion($question_text, $ide_labels);
        $helper = $this->getHelper('question');
        $choice_id = $helper->ask($this->input, $this->output, $question);
        $ide_uuid = array_search($choice_id, $ides_list, true);

        return $ide_uuid;
    }
}
