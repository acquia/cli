<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExceptionListener {

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  private $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  /**
   * ExceptionListener constructor.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   */
  public function setInput(InputInterface $input): void {
    $this->input = $input;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function setOutput(OutputInterface $output): void {
    $this->output = $output;
  }

  public function onConsoleError(ConsoleErrorEvent $event): void {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    $errorMessage = $error->getMessage();
    $io = new SymfonyStyle($this->input, $this->output);

    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $new_error_message = 'Your Cloud Platform API credentials are invalid.';
      $io->comment("Run <options=bold>acli auth:login</> to reset your API credentials.");
    }

    if ($error instanceof RuntimeException) {
      switch ($errorMessage) {
        case 'Not enough arguments (missing: "environmentId").':
        case 'Not enough arguments (missing: "environmentUuid").':
          $this->writeSiteAliasHelp($io);
          break;
        case 'Not enough arguments (missing: "applicationUuid").':
          $this->writeApplicationAliasHelp($io);
          break;
      }
    }

    if ($error instanceof AcquiaCliException) {
      switch ($errorMessage) {
        case '{applicationUuid} must be a valid UUID or application alias.':
          $this->writeApplicationAliasHelp($io);
          break;
        case '{environmentId} must be a valid UUID or site alias.':
        case '{environmentUuid} must be a valid UUID or site alias.':
          $this->writeSiteAliasHelp($io);
          break;
      }
    }

    if ($error instanceof ApiErrorException) {
      switch ($errorMessage) {
        case "There are no available Cloud IDEs for this application.\n":
          $io->comment("Delete an existing IDE via <options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.");
          $this->writeSupportTicketHelp($io);
          break;
        // @todo Remove after CXAPI-8261 is closed.
        case "The Cloud IDE is being deleted.\n":
          $new_error_message = "The Cloud IDE will be deleted momentarily. This process usually takes a few minutes." . PHP_EOL;
          break;
        default:
          $new_error_message = 'Cloud Platform API returned an error: ' . $errorMessage;
      }
      $io->comment("You can learn more about Cloud Platform API at <href=https://docs.acquia.com/cloud-platform/develop/api/>docs.acquia.com</>");
    }

    $io->comment("You can find Acquia CLI documentation at <href=https://docs.acquia.com/acquia-cli/>docs.acquia.com</>");
    if (isset($new_error_message)) {
      $event->setError(new AcquiaCliException($new_error_message, [], $exitCode));
    }
  }

  /**
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   */
  protected function writeApplicationAliasHelp(SymfonyStyle $io): void {
    $io->comment('<options=bold>applicationUuid</> can also be an application alias. E.g. <options=bold>myapp</>.');
    $io->comment('Run <options=bold>acli remote:aliases:list</> to see a list of all available aliases.');
  }

  /**
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   */
  protected function writeSiteAliasHelp(SymfonyStyle $io): void {
    $io->comment('<options=bold>environmentId</> can also be an site alias. E.g. <options=bold>myapp.dev</>.');
    $io->comment('Run <options=bold>acli remote:aliases:list</> to see a list of all available aliases.');
  }

  /**
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   */
  protected function writeSupportTicketHelp(SymfonyStyle $io): void {
    $io->comment("You may also <href=https://insight.acquia.com/support/tickets/new?product=p:ride>submit a support ticket</> to ask for more information.");
  }

}
