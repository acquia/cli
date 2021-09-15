<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

class ExceptionListener {

  /**
   * @var string
   */
  private $messagesBgColor = 'blue';

  /**
   * @var string
   */
  private $messagesFgColor = 'white';

  /**
   * @var string[]
   */
  private $helpMessages = [];

  /**
   * @param \Symfony\Component\Console\Event\ConsoleErrorEvent $event
   */
  public function onConsoleError(ConsoleErrorEvent $event): void {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    $errorMessage = $error->getMessage();

    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $new_error_message = 'Your Cloud Platform API credentials are invalid.';
      $this->helpMessages[] = "Run <bg={$this->messagesBgColor};fg={$this->messagesFgColor};options=bold>acli auth:login</> to reset your API credentials.";
    }

    if ($error instanceof RuntimeException) {
      switch ($errorMessage) {
        case 'Not enough arguments (missing: "environmentId").':
        case 'Not enough arguments (missing: "environmentUuid").':
          $this->writeSiteAliasHelp();
          break;
        case 'Not enough arguments (missing: "applicationUuid").':
          $this->writeApplicationAliasHelp();
          break;
      }
    }

    if ($error instanceof AcquiaCliException) {
      switch ($errorMessage) {
        case '{applicationUuid} must be a valid UUID or application alias.':
          $this->writeApplicationAliasHelp();
          break;
        case '{environmentId} must be a valid UUID or site alias.':
        case '{environmentUuid} must be a valid UUID or site alias.':
          $this->writeSiteAliasHelp();
          break;
      }
    }

    if ($error instanceof ApiErrorException) {
      switch ($errorMessage) {
        case "There are no available Cloud IDEs for this application.\n":
          $this->helpMessages[] = "Delete an existing IDE via <bg={$this->messagesBgColor};fg={$this->messagesFgColor};options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.";
          break;
        default:
          $new_error_message = 'Cloud Platform API returned an error: ' . $errorMessage;
          $this->helpMessages[] = "You can learn more about Cloud Platform API at https://docs.acquia.com/cloud-platform/develop/api/";
      }
    }

    $this->helpMessages[] = "You can find Acquia CLI documentation at https://docs.acquia.com/acquia-cli/";
    $this->writeUpdateHelp($event);
    $this->writeSupportTicketHelp($event);

    if ($application = $event->getCommand()) {
      /** @var \Acquia\Cli\Application $application */
      $application = $event->getCommand()->getApplication();
      $application->setHelpMessages($this->helpMessages);
    }

    if (isset($new_error_message)) {
      $event->setError(new AcquiaCliException($new_error_message, [], $exitCode));
    }
  }

  /**
   *
   */
  protected function writeApplicationAliasHelp(): void {
    $this->helpMessages[] = "<bg={$this->messagesBgColor};options=bold>applicationUuid</> can also be an application alias. E.g. <bg={$this->messagesBgColor};fg={$this->messagesFgColor};options=bold>myapp</>." . PHP_EOL
      . "Run <bg={$this->messagesBgColor};options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   *
   */
  protected function writeSiteAliasHelp(): void {
    $this->helpMessages[] = "<bg={$this->messagesBgColor};options=bold>environmentId</> can also be a site alias. E.g. <bg={$this->messagesBgColor};fg={$this->messagesFgColor};options=bold>myapp.dev</>." . PHP_EOL
    . "Run <bg={$this->messagesBgColor};options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   * @param $event
   */
  protected function writeSupportTicketHelp(ConsoleErrorEvent $event): void {
    $message = "You can submit a support ticket at https://insight.acquia.com/support/tickets/new?product=p:cli";
    if (!$event->getOutput()->isVeryVerbose()) {
      $message .= PHP_EOL . "Please re-run the command with the <bg={$this->messagesBgColor};fg={$this->messagesFgColor};options=bold>-vvv</> flag and include the full command output in your support ticket.";
    }
    $this->helpMessages[] = $message;
  }

  /**
   * @param \Symfony\Component\Console\Event\ConsoleErrorEvent $event
   */
  protected function writeUpdateHelp(ConsoleErrorEvent $event): void {
    try {
      $command = $event->getCommand();
      if ($command
        && method_exists($command, 'checkForNewVersion')
        && $latest = $command->checkForNewVersion()
      ) {
        $message = "Acquia CLI {$latest} is available. Try updating via <bg={$this->messagesBgColor};fg={$this->messagesFgColor};options=bold>acli self-update</> and then run the command again.";
        $this->helpMessages[] = $message;
      }
      // This command may not exist during some testing.
    } catch (CommandNotFoundException $exception) {
    }
  }

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
