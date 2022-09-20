<?php

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\Acsf\AcsfListCommandBase;
use Acquia\Cli\Command\Api\ApiListCommandBase;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ListCommand.
 */
class ListCommand extends \Symfony\Component\Console\Command\ListCommand {

  protected function configure(): void {
    parent::configure();
    $this->setName('self:list')
      ->setAliases(['list']);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    foreach (['api', 'acsf'] as $prefix) {
      if ($input->getArgument('namespace') !== $prefix) {
        $all_commands = $this->getApplication()->all();
        foreach ($all_commands as $command) {
          if (
            !is_a($command, ApiListCommandBase::class)
            && !is_a($command, AcsfListCommandBase::class)
            && str_starts_with($command->getName(), $prefix . ':')
          ) {
            $command->setHidden();
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
