<?php

namespace Acquia\Ads\Command;

use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends \Symfony\Component\Console\Command\ListCommand {

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = new DescriptorHelper();
        $helper->describe($output, $this->getApplication(), [
          'format' => $input->getOption('format'),
          'raw_text' => $input->getOption('raw'),
          'namespace' => $input->getArgument('namespace'),
        ]);
        // @todo Hide commands in the API namespace.

        return 0;
    }
}
