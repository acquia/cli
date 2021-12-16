<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class ComposerScriptsListener {

  /**
   * Before a console command is executed, execute a corresponding script from a local composer.json.
   *
   * @param ConsoleCommandEvent $event
   */
  public function onConsoleCommand(ConsoleCommandEvent $event): void {
    $this->executeComposerScripts($event, 'pre');
  }

  /**
   * When a console command terminates successfully, execute a corresponding script from a local composer.json.
   *
   * @param ConsoleTerminateEvent $event
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    if ($event->getExitCode() === 0) {
      $this->executeComposerScripts($event, 'post');
    }
  }

  /**
   * @param ConsoleTerminateEvent|ConsoleCommandEvent $event
   * @param string $prefix Added to the Composer script name. Expected values are 'pre' or 'post'.
   */
  private function executeComposerScripts($event, $prefix) {
    /** @var CommandBase $command */
    $command = $event->getCommand();
    // If a command has the --no-script option and it's passed, do not execute post scripts.
    if ($event->getInput()->hasOption('no-script') && $event->getInput()->getOption('no-scripts')) {
      return;
    }
    // Only successful commands should be executed.
    if (is_a($command, CommandBase::class)) {
      $composer_json_filepath = Path::join($command->getRepoRoot(), 'composer.json');
      if (file_exists($composer_json_filepath)) {
        $composer_json = json_decode($command->localMachineHelper->readFile($composer_json_filepath), TRUE);
        // Protect against invalid JSON.
        if ($composer_json) {
          $command_name = $command->getName();
          // Replace colons with hyphens. E.g., pull:db becomes pull-db.
          $script_name = $prefix . '-acli-' . str_replace(':', '-', $command_name);
          if (array_key_exists('scripts', $composer_json) && array_key_exists($script_name, $composer_json['scripts'])) {
            $event->getOutput()->writeln("Executing composer script `$script_name` defined in `$composer_json_filepath`", OutputInterface::VERBOSITY_VERBOSE);
            $event->getOutput()->writeln($script_name);
            $command->localMachineHelper->execute(['composer', 'run-script', $script_name]);
          }
          else {
            $event->getOutput()->writeln("Notice: Composer script `$script_name` does not exist in `$composer_json_filepath`, skipping. This is not an error.", OutputInterface::VERBOSITY_VERBOSE);
          }
        }
      }
    }
  }

}
