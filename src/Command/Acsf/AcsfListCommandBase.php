<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Acsf;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AcsfListCommandBase extends CommandBase
{
    protected string $namespace;

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getApplication()->find('list');
        $arguments = [
            'command' => 'list',
            'namespace' => 'acsf',
        ];
        $listInput = new ArrayInput($arguments);

        return $command->run($listInput, $output);
    }
}
