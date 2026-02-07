<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Self;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'self:make-docs', description: 'Generate documentation for all ACLI commands (Added in 1.25.0).', hidden: true)]
final class MakeDocsCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'The format to describe the docs in.', 'rst');
        $this->addOption('dump', 'd', InputOption::VALUE_OPTIONAL, 'Dump docs to directory (implies JSON format)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new DescriptorHelper();

        if (!$input->getOption('dump')) {
            $helper->describe($output, $this->getApplication(), [
                'format' => $input->getOption('format'),
            ]);
            return Command::SUCCESS;
        }

        $docs_dir = $input->getOption('dump');
        $this->localMachineHelper->getFilesystem()->mkdir($docs_dir);
        $buffer = new BufferedOutput();
        $helper->describe($buffer, $this->getApplication(), [
            'format' => 'json',
        ]);
        $commands = json_decode($buffer->fetch(), true);
        $index = [];
        foreach ($commands['commands'] as $command) {
            if ($command['definition']['hidden'] ?? false) {
                continue;
            }
            $filename = $command['name'] . '.json';
            $command['help'] = (new OutputFormatter())->format($command['help']);
            $index[] = [
                'command' => $command['name'],
                'help' => $command['help'],
                'path' => $filename,
                'usage' => $command['usage'][0],
            ];
            file_put_contents("$docs_dir/$filename", json_encode($command));
        }
        file_put_contents("$docs_dir/index.json", json_encode($index));
        return Command::SUCCESS;
    }
}
