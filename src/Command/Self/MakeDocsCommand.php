<?php

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Descriptor\ReStructuredTextDescriptor;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MakeDocsCommand.
 */
class MakeDocsCommand extends CommandBase {

  protected static $defaultName = 'self:make-docs';

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
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $helper = new DescriptorHelper();
    $helper->register('rst', new ReStructuredTextDescriptor());

    $helper->describe($output, $this->getApplication(), [
      'format' => 'rst',
    ]);

    return 0;
  }

}
