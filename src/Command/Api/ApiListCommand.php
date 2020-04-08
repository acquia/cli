<?php

namespace Acquia\Ads\Command\Api;

use Acquia\Ads\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApiListCommand extends CommandBase {

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('api')
          ->setDescription('List all commands in the api namespace');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('list');
        $arguments = [
          'command' => 'list',
          'namespace' => 'api',
        ];

        $list_input = new ArrayInput($arguments);
        return $command->run($list_input, $output);
    }
}
