<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

class TerminateListener {

    /**
     * When a console command terminates, execute a corresponding script from a local composer.json.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
        /** @var CommandBase $command */
        $command = $event->getCommand();
        if ($event->getInput()->hasOption('no-script') && $event->getInput()->getOption('no-scripts')) {
            return;
        }
        if (is_a($command, CommandBase::class)) {
            $composer_json_filepath = Path::join($command->getRepoRoot(), 'composer.json');
            if (file_exists($composer_json_filepath)) {
                $composer_json = json_decode($command->localMachineHelper->readFile($composer_json_filepath), TRUE);
                if ($composer_json) {
                    $command_name = $command->getName();
                    $script_name = 'post-acli-' . $command_name;
                    if (array_key_exists('scripts', $composer_json) && array_key_exists($script_name, $composer_json['scripts'])) {
                        $event->getOutput()->writeln("Executing composer script `$script_name` defined in `$composer_json_filepath`", OutputInterface::VERBOSITY_VERBOSE);
                        $event->getOutput()->writeln($script_name);
                        $command->localMachineHelper->execute(['composer', 'run-script', $script_name]);
                    }
                    else {
                        $event->getOutput()->writeln("Composer script `$script_name` does not exist in `$composer_json_filepath`, skipping.", OutputInterface::VERBOSITY_VERBOSE);
                    }
                }
            }
        }
    }
}