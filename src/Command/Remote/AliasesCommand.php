<?php

namespace Acquia\Ads\Command\Remote;

use Acquia\Ads\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DrushCommand
 * A command to proxy Drush commands on an environment using SSH
 * @package Acquia\Ads\Commands\Remote
 */
class AliasesCommand extends SshCommand
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('remote:aliases')
          ->setDescription('');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $applications_resource = new Applications($acquia_cloud_client);
        $customer_applications = $applications_resource->getAll();
        $environments_resource = new Environments($acquia_cloud_client);
        $count = count($customer_applications);
        $this->output->writeln("Fetching aliases for $count applications from Acquia Cloud...");

        $table = new Table($this->output);
        $table->setHeaders(['Environment Alias', 'Application', 'Environment UUID']);

        foreach ($customer_applications as $customer_application) {
            $this->logger->debug("Fetching aliases for environments belonging to {$customer_application->name} application.");
            $site_id = $customer_application->hosting->id;
            $parts = explode(':', $site_id);
            $site_prefix = $parts[1];
            $environments = $environments_resource->getAll($customer_application->uuid);
            foreach ($environments as $environment) {
                $alias = $site_prefix . '.' . $environment->name;
                $table->addRow([$customer_application->name, $alias, $environment->uuid]);
            }
        }

        $table->render();

        return 0;
    }
}
