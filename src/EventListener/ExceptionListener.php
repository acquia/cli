<?php

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
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
  private $messagesBgColor;

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
   * @param \Symfony\Component\Console\Event\ConsoleErrorEvent $event
   */
  public function onConsoleError(ConsoleErrorEvent $event): void {
    $exitCode = $event->getExitCode();
    $error = $event->getError();
    $errorMessage = $error->getMessage();
    $io = new SymfonyStyle($this->input, $this->output);
    $this->messagesBgColor = 'blue';
    $block_messages = [];

    // Make OAuth server errors more human-friendly.
    if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
      $new_error_message = 'Your Cloud Platform API credentials are invalid.';
      $block_messages[] = "Run <bg={$this->messagesBgColor};options=bold>acli auth:login</> to reset your API credentials.";
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
          $block_messages[] = "Delete an existing IDE via <bg={$this->messagesBgColor};options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.";
          $this->writeSupportTicketHelp();
          break;
        default:
          $new_error_message = 'Cloud Platform API returned an error: ' . $errorMessage;
      }
      $block_messages[] = "You can learn more about Cloud Platform API at <bg={$this->messagesBgColor};href=https://docs.acquia.com/cloud-platform/develop/api/>https://docs.acquia.com/cloud-platform/develop/api/</>";
    }

    $block_messages[] = "You can find Acquia CLI documentation at <bg={$this->messagesBgColor};href=https://docs.acquia.com/acquia-cli/>https://docs.acquia.com/acquia-cli/</>";
    if ($block_messages) {
      $output_style = new OutputFormatterStyle(NULL, $this->messagesBgColor);
      $this->output->getFormatter()->setStyle('help', $output_style);
      $io->block($block_messages, 'note', 'help', ' ', FALSE, FALSE);
    }
    if (isset($new_error_message)) {
      $event->setError(new AcquiaCliException($new_error_message, [], $exitCode));
    }
  }

  /**
   *
   */
  protected function writeApplicationAliasHelp(): void {
    $block_messages[] = "<bg={$this->messagesBgColor};options=bold>applicationUuid</> can also be an application alias. E.g. <bg={$this->messagesBgColor};options=bold>myapp</>.";
    $block_messages[] = "Run <bg={$this->messagesBgColor};options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   *
   */
  protected function writeSiteAliasHelp(): void {
    $block_messages[] = "<bg={$this->messagesBgColor};options=bold>environmentId</> can also be a site alias. E.g. <bg={$this->messagesBgColor};options=bold>myapp.dev</>.";
    $block_messages[] = "Run <bg={$this->messagesBgColor};options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
  }

  /**
   *
   */
  protected function writeSupportTicketHelp(): void {
    $block_messages[] = "You may also to ask for more information.";
    $block_messages[] = "<bg={$this->messagesBgColor};href=https://insight.acquia.com/support/tickets/new?product=p:ride>https://insight.acquia.com/support/tickets/new?product=p:ride</>";
  }

}
