<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MakeDocsCommand.
 */
class MakeDocsCommand extends CommandBase {

  protected static $defaultName = 'make:docs';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Generate documentation for all ACLI commands')
      ->setHidden(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $helper = new DescriptorHelper();
    $helper->describe($output, $this->getApplication(), [
      'format' => 'md',
    ]);

    return 0;
  }

}
