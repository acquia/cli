<?php

namespace Acquia\Ads\Command\Remote;

use Acquia\Ads\Application\ApplicationAwareInterface;
use Acquia\Ads\Application\ApplicationAwareTrait;
use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exception\AdsException;
use Acquia\Ads\Helpers\LocalMachineHelper;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

/**
 * Class SSHBaseCommand
 * Base class for Ads commands that deal with sending SSH commands
 * @package Acquia\Ads\Commands\Remote
 */
abstract class SshBaseCommand extends CommandBase implements ApplicationAwareInterface
{

    use ApplicationAwareTrait;

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
     * @return $this
     */
    protected function setProgressAllowed($allowed = true)
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
    protected function executeCommand(array $command_args)
    {
        $command_summary = $this->getCommandSummary($command_args);

        // Remove site_env arg.
        unset($command_args['site_env']);
        $ssh_data = $this->sendCommandViaSsh($command_args);

        /** @var \Acquia\Ads\AdsApplication $application */
        $application = $this->getApplication();
        $application->getLogger()->notice('Command: {command} [Exit: {exit}]', [
          'env'     => $this->environment->name,
          'command' => $command_summary,
          'exit'    => $ssh_data['exit_code'],
        ]);

        if ($ssh_data['exit_code'] != 0) {
            throw new AdsException($ssh_data['output']);
        }

        return $ssh_data['exit_code'];
    }

    /**
     * Sends a command to an environment via SSH.
     *
     * @param array $command The command to be run on the platform
     */
    protected function sendCommandViaSsh($command)
    {
        $command = array_merge($this->getConnectionArgs(), $command);
        return $this->local_machine_helper->execute(
            $command,
            $this->getOutputCallback(),
            $this->progressAllowed
        );
    }

    /**
     * Validates that the environment's connection mode is appropriately set
     *
     * @param Environment $environment
     */
    protected function validateEnvironment($environment)
    {
        // Only warn in dev / multidev
        if ($environment->isDevelopment()) {
            $this->validateConnectionMode($environment->get('connection_mode'));
        }
    }

    /**
     * Validates that the environment is using the correct connection mode
     *
     * @param string $mode
     */
    protected function validateConnectionMode($mode)
    {
        if ((!$this->getConfig()->get('hide_git_mode_warning')) && ($mode == 'git')) {
            $this->log()->warning(
                'This environment is in read-only Git mode. If you want to make changes to the codebase of this site '
                . '(e.g. updating modules or plugins), you will need to toggle into read/write SFTP mode first.'
            );
        }
    }

    /**
     * Outputs a message if Ads is in test mode and uses it to mock the command's response
     *
     * @string $ssh_command
     * @return string[] $response Elements as follow:
     *         string output    The output from the command run
     *         string exit_code The status code returned by the command run
     */
    private function divertForTestMode($ssh_command)
    {
        $output = "Ads is in test mode. SSH commands will not be sent over the wire. "
          . PHP_EOL . "SSH Command: ${ssh_command}";
        $container = $this->getContainer();
        if ($container->has('output')) {
            $container->get('output')->write($output);
        }
        return [
          'output' => $output,
          'exit_code' => 0
        ];
    }

    /**
     * Escape the command-line args
     *
     * @param string[] $args All of the arguments to escape
     * @param string[]
     */
    private function escapeArguments($args)
    {
        return array_map(
            function ($arg) {
                return $this->escapeArgument($arg);
            },
            $args
        );
    }

    /**
     * Escape one command-line arg
     *
     * @param string $arg The argument to escape
     * @return string
     */
    private function escapeArgument($arg)
    {
        // Omit escaping for simple args.
        if (preg_match('/^[a-zA-Z0-9_-]*$/', $arg)) {
            return $arg;
        }
        return ProcessUtils::escapeArgument($arg);
    }

    /**
     * Return the first item of the $command_args that is not an option.
     *
     * @param array $command_args
     * @return string
     */
    private function firstArguments($command_args)
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
     * @param boolean $usesTty
     * @return \Closure
     */
    private function getOutputCallback()
    {
        if ($this->local_machine_helper->useTty() === false) {
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
     * @return string
     */
    private function getCommandSummary($command_args)
    {
        return $this->firstArguments($command_args);
    }

    /**
     * @return array SSH connection string
     */
    private function getConnectionArgs()
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
