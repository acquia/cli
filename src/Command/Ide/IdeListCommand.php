<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeListCommand
 */
class IdeListCommand extends CommandBase
{

    use ExecTrait;

    /** @var \stdClass */
    private $localProjectInfo;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ide:list')->setDescription('Please select the application to list the IDEs for.');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $application_uuid = $this->determineCloudApplication();

        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $ides_resource = new Ides($acquia_cloud_client);
        $application_ides = $ides_resource->getAll($application_uuid);

        $table = new Table($output);
        $table->setHeaders(['Label', 'Web URL', 'IDE URL']);
        foreach ($application_ides as $ide) {
            $table->addRow([$ide->label, $ide->links->web->href, $ide->links->ide->href]);
        }
        $table->render();

        return 0;
    }
}
