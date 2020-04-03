<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\CloudApiClient;
use Acquia\Ads\Exec\ExecTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CreateProjectCommand
 *
 * @package Grasmash\YamlCli\Command
 */
class ApiCommand extends CommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('api')
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
        $client = new CloudApiClient($this->getApplication()->getDatastore());
        $response = $client->request();
        $this->output->writeln($response->getBody()->getContents());

        return 0;
    }
}
