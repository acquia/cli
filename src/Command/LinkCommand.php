<?php

namespace Acquia\Ads\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LinkCommand.
 */
class LinkCommand extends CommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure() {
        $this->setName('link')->setDescription('Associate your project with an Acquia Cloud application');
        // @todo Add option to allow specifying application uuid.
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     * @throws \Acquia\Ads\Exception\AdsException
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->validateCwdIsValidDrupalProject();
        // @todo Indicate if the current local repo is already associated with a cloud
        // application. Confirm to overwrite.

        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $application_uuid = $this->promptChooseApplication($this->input, $this->output, $acquia_cloud_client);
        $this->saveLocalConfigCloudAppUuid($application_uuid);

        return 0;
    }

}
