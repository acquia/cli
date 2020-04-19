<?php

namespace Acquia\Ads\Command\Api;

use Acquia\Ads\CloudApiClient;
use Acquia\Ads\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ApiCommandBase
 */
class ApiCommandBase extends CommandBase
{
    /** @var string */
    protected $method;

    /** @var array */
    protected $responses;

    /** @var array */
    protected $servers;

    /** @var String */
    protected $path;

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new CloudApiClient($this->getApplication()->getDatastore());
        $api_url = $this->servers[0]['url'];

        $options = $input->getOptions();
        $default_options = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'];
        // Filter out default Command options.
        $request_options = array_diff(array_keys($options), $default_options);
        $request_options = array_intersect_key($options, array_flip($request_options));

        // Build query from non-null options.
        $query = [];
        foreach ($request_options as $key => $value) {
            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        // @todo Create a body for post commands.
        $path = $this->getRequestPath($input);
        // @todo Replace $client with Acquia Cloud Connector.
        $response = $client->request($api_url, $path, $query, $this->method);
        $this->output->writeln($response->getBody()->getContents());

        return 0;
    }

    /**
     * @param string $method
     */
    public function setMethod($method): void
    {
        $this->method = $method;
    }

    /**
     * @param array $responses
     */
    public function setResponses($responses): void
    {
        $this->responses = $responses;
    }

    /**
     * @param array $servers
     */
    public function setServers($servers): void
    {
        $this->servers = $servers;
    }

    /**
     * @param string $path
     */
    public function setPath($path): void
    {
        $this->path = $path;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return string
     */
    protected function getRequestPath(InputInterface $input): string
    {
        $path = $this->path;
        $arguments = $input->getArguments();
        // The command itself is the first argument. Remove it.
        array_shift($arguments);
        foreach ($arguments as $key => $value) {
            $token = '{' . $key . '}';
            if (strpos($path, $token) !== false) {
                $path = str_replace($token, $value, $this->path);
            }
        }

        return $path;
    }
}
