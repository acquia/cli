<?php

namespace Acquia\Ads\Command\Remote;

use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DrushCommand
 * A command to proxy Drush commands on an environment using SSH
 * @package Acquia\Ads\Commands\Remote
 */
class DrushCommand extends SSHBaseCommand
{
    /**
     * @inheritdoc
     */
    protected $command = 'drush';

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('remote:drush')
            ->setDescription('Runs a Drush command remotely on a application\'s environment.')
            ->addArgument('site_env', InputArgument::REQUIRED, 'Site & environment in the format `site-name.env`')
            ->addArgument('drush_command', InputArgument::REQUIRED, 'Drush command')
            ->addUsage(" <site>.<env> -- <command> Runs the Drush command <command> remotely on <site>'s <env> environment.")
            ->addUsage("@usage <site>.<env> --progress -- <command> Runs a Drush command with a progress bar");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @todo Validate the arg format.
        $site_env = $input->getArgument('site_env');
        $site_env_parts = explode('.', $site_env);
        $drush_site = $site_env_parts[0];
        $drush_env = $site_env_parts[1];

        // @todo Add error handling.
        $environment = $this->getEnvFromAlias($drush_site, $drush_env);

        $arguments = $input->getArguments();
        array_shift($arguments);
        return $this->executeCommand($arguments);
    }

    /**
     * @param string $drush_site
     * @param string $drush_env
     *
     * @return \AcquiaCloudApi\Response\EnvironmentResponse
     */
    protected function getEnvFromAlias(
      $drush_site,
      $drush_env
    ): EnvironmentResponse {
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $applications_resource = new Applications($acquia_cloud_client);
        $customer_applications = $applications_resource->getAll();
        $environments_resource = new Environments($acquia_cloud_client);
        foreach ($customer_applications as $customer_application) {
            $site_id = $customer_application->hosting->id;
            $parts = explode(':', $site_id);
            $site_prefix = $parts[1];
            if ($site_prefix === $drush_site) {
                $environments = $environments_resource->getAll($customer_application->uuid);
                foreach ($environments as $environment) {
                    if ($environment->name === $drush_env) {
                        return $environment;
                    }
                }
            }
        }

        return null;
    }
}
