<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Attribute\RequireAuth;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Codebases;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'ide:list:mine', description: 'List Cloud IDEs belonging to you')]
final class IdeListMineCommand extends IdeCommandBase
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $ides = new Ides($acquiaCloudClient);
        $accountIdes = $ides->getMine();
        $codebaseResource = new Codebases($acquiaCloudClient);
        $applicationResource = new Applications($acquiaCloudClient);

        if (count($accountIdes)) {
            $table = new Table($output);
            $table->setStyle('borderless');
            $table->setHeaders(['IDEs']);
            foreach ($accountIdes as $ide) {
                if (isset($ide->links->application)) {
                    $sub = "Application";
                    $appUrlParts = explode('/', $ide->links->application->href);
                    $appUuid = end($appUrlParts);
                    $application = $applicationResource->get($appUuid);
                    $name = $application->name;
                    $url = str_replace('/api', '/a', $ide->links->application->href);
                } elseif (isset($ide->links->codebase)) {
                    $sub = "Codebase";
                    $codebaseUrlParts = explode('/', $ide->links->codebase->href);
                    $codebaseUuid = end($codebaseUrlParts);
                    $codebase = $codebaseResource->get($codebaseUuid);
                    $name = $codebase->label;
                    $url = str_replace('/api', '/a', $ide->links->codebase->href);
                } else {
                    $name = 'N/A';
                    $url = 'N/A';
                    $sub = 'N/A';
                }

                $table->addRows([
                    ["<comment>$ide->label</comment>"],
                    ["UUID: $ide->uuid"],
                    ["$sub: <href=$url>$name</>"],
                    ["Subscription: {$name}"],
                    ["IDE URL: <href={$ide->links->ide->href}>{$ide->links->ide->href}</>"],
                    ["Web URL: <href={$ide->links->web->href}>{$ide->links->web->href}</>"],
                    new TableSeparator(),
                ]);
            }
            $table->render();
        } else {
            $output->writeln('No IDE exists for your account.');
        }

        return Command::SUCCESS;
    }
}
