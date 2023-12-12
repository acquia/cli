<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\Acsf\AcsfListCommandBase;
use Acquia\Cli\Command\Api\ApiListCommandBase;
use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list', description: NULL, aliases: ['self:list'])]
final class ListCommand extends CommandBase {

  protected function configure(): void {
    $this
      ->setName('list')
      ->setDefinition([
        new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace name', NULL, fn () => array_keys((new ApplicationDescription($this->getApplication()))->getNamespaces())),
        new InputOption('raw', NULL, InputOption::VALUE_NONE, 'To output raw command list'),
        new InputOption('format', NULL, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt', fn () => (new DescriptorHelper())->getFormats()),
        new InputOption('short', NULL, InputOption::VALUE_NONE, 'To skip describing commands\' arguments'),
      ])
      ->setDescription('List commands')
      ->setHelp(<<<'EOF'
The <info>%command.name%</info> command lists all commands:

  <info>%command.full_name%</info>

You can also display the commands for a specific namespace:

  <info>%command.full_name% test</info>

You can also output the information in other formats by using the <comment>--format</comment> option:

  <info>%command.full_name% --format=xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>%command.full_name% --raw</info>
EOF
      );
  }

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
