<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\Acsf\AcsfListCommandBase;
use Acquia\Cli\Command\Api\ApiListCommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list', description: NULL, aliases: ['self:list'])]
final class ListCommand extends \Symfony\Component\Console\Command\ListCommand {

  protected function execute(InputInterface $input, OutputInterface $output): int {
    foreach (['api', 'acsf'] as $prefix) {
      if ($input->getArgument('namespace') !== $prefix) {
        $allCommands = $this->getApplication()->all();
        foreach ($allCommands as $command) {
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
      'namespace' => $input->getArgument('namespace'),
      'raw_text' => $input->getOption('raw'),
    ]);

    return Command::SUCCESS;
  }

}
