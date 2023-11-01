<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelloWorldCommand extends CommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'hello-world';

  protected function configure(): void {
    $this->setDescription('Test command used for asserting core functionality')
      ->setHidden();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io->success('Hello world!');

    return Command::SUCCESS;
  }

}
