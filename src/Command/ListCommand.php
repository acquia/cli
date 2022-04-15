<?php

namespace Acquia\Cli\Command;

use Acquia\Cli\Command\Acsf\AcsfListCommandBase;
use Acquia\Cli\Command\Api\ApiListCommandBase;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ListCommand.
 */
class ListCommand extends \Symfony\Component\Console\Command\ListCommand {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach (['api', 'acsf'] as $prefix) {
      if ($input->getArgument('namespace') !== $prefix) {
        $all_commands = $this->getApplication()->all();
        foreach ($all_commands as $command) {
          if (
            !is_a($command, ApiListCommandBase::class)
            && !is_a($command, AcsfListCommandBase::class)
            && strpos($command->getName(), $prefix . ':') !== FALSE
          ) {
            $command->setHidden(TRUE);
          }
        }
      }
    }

    $helper = new DescriptorHelper();
    $helper->describe($output, $this->getApplication(), [
      'format' => $input->getOption('format'),
      'raw_text' => $input->getOption('raw'),
      'namespace' => $input->getArgument('namespace'),
    ]);

    return 0;
  }

}
