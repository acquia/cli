<?php

namespace Acquia\Ads\Command\Remote;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exception\AdsException;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;

/**
 * Class SSHBaseCommand
 * Base class for Ads commands that deal with sending SSH commands
 * @package Acquia\Ads\Commands\Remote
 */
abstract class SshBaseCommand extends CommandBase
{

    /**
     * @var string Name of the command to be run as it will be used on server
     */
    protected $command = '';
    /**
     * @var \AcquiaCloudApi\Response\EnvironmentResponse
     */
    protected $environment;

    /**
     * @var bool
     */
    protected $progressAllowed;

    /**
     * progressAllowed sets the field that controls whether a progress bar
     * may be displayed when a program is executed. If allowed, a progress
     * bar will be used in tty mode.
     *
     * @param type|bool $allowed
     *
     * @return $this
     */
    protected function setProgressAllowed($allowed = true): self
    {
        $this->progressAllowed = $allowed;

        return $this;
    }

    /**
     * Execute the command remotely
     *
     * @param array $command_args
     *
     * @return int
     * @throws \Acquia\Ads\Exception\AdsException
     */
    protected function executeCommand(array $command_args): int
    {
        $command_summary = $this->getCommandSummary($command_args);

        // Remove site_env arg.
        unset($command_args['site_env']);
        $process = $this->sendCommandViaSsh($command_args);

        /** @var \Acquia\Ads\AdsApplication $application */
        $application = $this->getApplication();
        $application->getLogger()->notice('Command: {command} [Exit: {exit}]', [
          'env' => $this->environment->name,
          'command' => $command_summary,
          'exit' => $process->getExitCode(),
        ]);

        if (!$process->isSuccessful()) {
            throw new AdsException($process->getOutput());
        }

        return $process->getExitCode();
    }

    /**
     * Sends a command to an environment via SSH.
     *
     * @param array $command The command to be run on the platform
     *
     * @return
     */
    protected function sendCommandViaSsh($command)
    {
        $command = array_merge($this->getConnectionArgs(), $command);

        return $this->getApplication()
          ->getLocalMachineHelper()
          ->execute($command, $this->getOutputCallback());
    }

    /**
     * Return the first item of the $command_args that is not an option.
     *
     * @param array $command_args
     *
     * @return string
     */
    private function firstArguments($command_args): string
    {
        $result = '';
        while (!empty($command_args)) {
            $first = array_shift($command_args);
            if (strlen($first) && $first[0] == '-') {
                return $result;
            }
            $result .= " $first";
        }

        return $result;
    }

    /**
     * @return \Closure
     */
    private function getOutputCallback(): callable
    {
        if ($this->getApplication()->getLocalMachineHelper()->useTty() === false) {
            $output = $this->output;

            return function ($type, $buffer) use ($output) {
                // @todo Separate the stderr output.
                $output->write($buffer);
            };
        }

        return function ($type, $buffer) {
        };
    }

    /**
     * Return a summary of the command that does not include the
     * arguments. This avoids potential information disclosure in
     * CI scripts.
     *
     * @param array $command_args
     *
     * @return string
     */
    private function getCommandSummary($command_args): string
    {
        return $this->firstArguments($command_args);
    }

    /**
     * @return array SSH connection string
     */
    private function getConnectionArgs(): array
    {
        return [
          'ssh',
          '-T',
          $this->environment->sshUrl,
          '-o StrictHostKeyChecking=no',
          '-o AddressFamily inet',
        ];
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
        // @todo Speed this up with some kind of caching.
        $this->logger->debug("Searching for an environment matching alias $drush_site.$drush_env.");
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $applications_resource = new Applications($acquia_cloud_client);
        $customer_applications = $applications_resource->getAll();
        $environments_resource = new Environments($acquia_cloud_client);
        foreach ($customer_applications as $customer_application) {
            $site_id = $customer_application->hosting->id;
            $parts = explode(':', $site_id);
            $site_prefix = $parts[1];
            if ($site_prefix === $drush_site) {
                $this->logger->debug("Found application matching $drush_site. Searching environments...");
                $environments = $environments_resource->getAll($customer_application->uuid);
                foreach ($environments as $environment) {
                    if ($environment->name === $drush_env) {
                        $this->logger->debug("Found environment matching $drush_env.");

                        return $environment;
                    }
                }
            }
        }

        return null;
    }
}
