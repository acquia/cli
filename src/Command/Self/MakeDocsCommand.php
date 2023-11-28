<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'self:make-docs', description: 'Generate documentation for all ACLI commands', hidden: TRUE)]
final class MakeDocsCommand extends CommandBase {

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $helper = new DescriptorHelper();

    $helper->describe($output, $this->getApplication(), [
      'format' => 'rst',
    ]);

    return Command::SUCCESS;
  }

}
