<?php

declare(strict_types=1);

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class ComposerScriptsListener
{
    /**
     * Before a console command is executed, execute a corresponding script from
     * a local composer.json.
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->executeComposerScripts($event, 'pre');
    }

    /**
     * When a console command terminates successfully, execute a corresponding
     * script from a local composer.json.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getExitCode() === 0) {
            $this->executeComposerScripts($event, 'post');
        }
    }

    /**
     * @param string $prefix Added to the Composer script name. Expected values
     *   are 'pre' or 'post'.
     */
    private function executeComposerScripts(ConsoleCommandEvent|ConsoleTerminateEvent $event, string $prefix): void
    {
        /** @var CommandBase $command */
        $command = $event->getCommand();
        if (
            $event->getInput()->hasOption('no-scripts') && $event->getInput()
                ->getOption('no-scripts')
        ) {
            return;
        }
        // Only successful commands should be executed.
        if (is_a($command, CommandBase::class)) {
            $composerJsonFilepath = Path::join($command->getProjectDir(), 'composer.json');
            if (file_exists($composerJsonFilepath)) {
                $composerJson = json_decode($command->localMachineHelper->readFile($composerJsonFilepath), true, 512, JSON_THROW_ON_ERROR);
                $commandName = $command->getName();
                // Replace colons with hyphens. E.g., pull:db becomes pull-db.
                $scriptName = $prefix . '-acli-' . str_replace(':', '-', $commandName);
                if (array_key_exists('scripts', $composerJson) && array_key_exists($scriptName, $composerJson['scripts'])) {
                    $event->getOutput()
                        ->writeln("Executing composer script `$scriptName` defined in `$composerJsonFilepath`", OutputInterface::VERBOSITY_VERBOSE);
                    $event->getOutput()->writeln($scriptName);
                    $command->localMachineHelper->execute([
                        'composer',
                        'run-script',
                        $scriptName,
                    ]);
                } else {
                    $event->getOutput()
                        ->writeln("Notice: Composer script `$scriptName` does not exist in `$composerJsonFilepath`, skipping. This is not an error.", OutputInterface::VERBOSITY_VERBOSE);
                }
            }
        }
    }
}
