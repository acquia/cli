<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'hello-world', description: 'Test command used for asserting core functionality')]
final class HelloWorldCommand extends CommandBase {

  protected function configure(): void {
    $this
      ->setHidden();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io->success('Hello world!');

    return Command::SUCCESS;
  }

}
