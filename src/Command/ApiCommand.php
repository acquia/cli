<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\CloudApiClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateProjectCommand
 *
 * @package Grasmash\YamlCli\Command
 */
class ApiCommand extends CommandBase
{
    protected $method;

    protected $responses;

    protected $servers;

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new CloudApiClient($this->getApplication()->getDatastore());
        $response = $client->request();
        $this->output->writeln($response->getBody()->getContents());

        return 0;
    }

    public function setMethod($method) {
        $this->method = $method;
    }

    public function setResponses($responses) {
        $this->responses = $responses;
    }

    public function setServers($servers) {
        $this->servers = $servers;
    }
}
