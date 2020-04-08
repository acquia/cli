<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeOpenCommand
 */
class IdeOpenCommand extends CommandBase
{

    use ExecTrait;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ide:open')
          ->setDescription('');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $application_uuid = $this->promptChooseApplication($input, $output, $acquia_cloud_client);

        // Create the IDE!
        $ides_resource = new Ides($acquia_cloud_client);
        // @todo List IDEs in choice question.

        return 0;
    }
}
