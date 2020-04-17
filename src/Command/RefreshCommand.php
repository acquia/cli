<?php

namespace Acquia\Ads\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RefreshCommand.
 */
class RefreshCommand extends CommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('refresh')
          ->setDescription('Copy database and files from one environment to another');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output->writeln("<comment>This is a command stub. The command logic has not been written yet.");
        return 0;
    }
}
