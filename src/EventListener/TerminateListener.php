<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

class TerminateListener {

  /**
   * When a console command terminates successfully, execute a corresponding script from a local composer.json.
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    /** @var CommandBase $command */
    $command = $event->getCommand();
    // If a command has the --no-script option and it's passed, do not execute post scripts.
    if ($event->getInput()->hasOption('no-script') && $event->getInput()->getOption('no-scripts')) {
      return;
    }
    // Only successful commands should be executed.
    if (is_a($command, CommandBase::class) && $event->getExitCode() === 0) {
      $composer_json_filepath = Path::join($command->getRepoRoot(), 'composer.json');
      if (file_exists($composer_json_filepath)) {
        $composer_json = json_decode($command->localMachineHelper->readFile($composer_json_filepath), TRUE);
        // Protect against invalid JSON.
        if ($composer_json) {
          $command_name = $command->getName();
          // Replace colons with hypens. E.g., pull:db becomes pull-db.
          $script_name = 'post-acli-' . str_replace(':', '-', $command_name);
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