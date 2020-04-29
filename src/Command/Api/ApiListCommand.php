<?php

namespace Acquia\Ads\Command\Api;

use Acquia\Ads\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApiListCommand extends CommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('api:list')->setDescription('List all commands in the api namespace');
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Unhide api:* commands.
        $api_commands = $this->getApplication()->all('api');
        foreach ($api_commands as $api_command) {
            $api_command->setHidden(false);
        }

        $command = $this->getApplication()->find('list');
        $arguments = [
          'command' => 'list',
          'namespace' => 'api',
        ];
        $list_input = new ArrayInput($arguments);

        return $command->run($list_input, $output);
    }
}
