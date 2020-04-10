<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class IdeCreateCommand
 */
class IdeCreateCommand extends CommandBase
{

    use ExecTrait;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ide:create')
          ->setDescription('create remote IDE for development');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cloud_application_uuid = $this->determineCloudApplication();

        $question = new Question('Please enter a label for your Remote IDE:');
        $helper = $this->getHelper('question');
        $ide_label = $helper->ask($input, $output, $question);

        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $ides_resource = new Ides($acquia_cloud_client);
        $response = $ides_resource->create($cloud_application_uuid, $ide_label);
        $this->output->writeln($response->message);

        return 0;
    }
}
