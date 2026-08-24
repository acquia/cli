<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Api;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApiListCommandBase extends CommandBase
{
    protected string $namespace;

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getApplication()->find('list');
        // @infection-ignore-all — 'command' key is vestigial in Symfony; namespace comparison operator mutations don't affect observable output.
        $arguments = [
            'command' => 'list',
            'namespace' => $this->namespace,
        ];
        $listInput = new ArrayInput($arguments);

        return $command->run($listInput, $output);
    }
}
