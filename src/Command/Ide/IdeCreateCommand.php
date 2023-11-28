<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

#[RequireAuth]
#[AsCommand(name: 'ide:create', description: 'Create a Cloud IDE')]
class IdeCreateCommand extends IdeCommandBase {

  private IdeResponse $ide;

  private Client $client;

  protected function configure(): void {
    $this->acceptApplicationUuid();
    $this->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The label for the IDE');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $cloudApplicationUuid = $this->determineCloudApplication();
    $checklist = new Checklist($output);
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $accountResource = new Account($acquiaCloudClient);
    $account = $accountResource->get();
    $default = "$account->first_name $account->last_name's IDE";
    $ideLabel = $this->determineOption('label', FALSE, $this->validateIdeLabel(...), NULL, $default);

    // Create it.
    $checklist->addItem('Creating your Cloud IDE');
    $idesResource = new Ides($acquiaCloudClient);
    $response = $idesResource->create($cloudApplicationUuid, $ideLabel);
    $checklist->completePreviousItem();

    // Get IDE info.
    $checklist->addItem('Getting IDE information');
    $this->ide = $this->getIdeFromResponse($response, $acquiaCloudClient);
    $ideUrl = $this->ide->links->ide->href;
    $checklist->completePreviousItem();

    // Wait!
    return $this->waitForDnsPropagation($ideUrl);
  }

  /**
   * Keep this public since it's used as a callback and static analysis tools
   * think it's unused.
   *
   * @todo use first-class callable syntax instead once we upgrade to PHP 8.1
   * @see https://www.php.net/manual/en/functions.first_class_callable_syntax.php
   */
  public function validateIdeLabel(string $label): string {
    $violations = Validation::createValidator()->validate($label, [
      new Regex(['pattern' => '/^[\w\' ]+$/', 'message' => 'Use only letters, numbers, and spaces']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    return $label;
  }

  private function waitForDnsPropagation(mixed $ideUrl): int {
    $ideCreated = FALSE;
    if (!$this->getClient()) {
      $this->setClient(new Client(['base_uri' => $ideUrl]));
    }
    $checkIdeStatus = function () use (&$ideCreated) {
      $response = $this->client->request('GET', '/health');
      if ($response->getStatusCode() === 200) {
        $ideCreated = TRUE;
      }
      return $ideCreated;
    };
    $doneCallback = function () use (&$ideCreated): void {
      if ($ideCreated) {
        $this->output->writeln('');
        $this->output->writeln('<info>Your IDE is ready!</info>');
      }
      $this->writeIdeLinksToScreen();
    };
    $spinnerMessage = 'Waiting for the IDE to be ready. This usually takes 2 - 15 minutes.';
    LoopHelper::getLoopy($this->output, $this->io, $this->logger, $spinnerMessage, $checkIdeStatus, $doneCallback);

    return Command::SUCCESS;
  }

  /**
   * Writes the IDE links to screen.
   */
  private function writeIdeLinksToScreen(): void {
    $this->output->writeln('');
    $this->output->writeln("<comment>Your IDE URL:</comment> <href={$this->ide->links->ide->href}>{$this->ide->links->ide->href}</>");
    $this->output->writeln("<comment>Your Drupal Site URL:</comment> <href={$this->ide->links->web->href}>{$this->ide->links->web->href}</>");
    // @todo Prompt to open browser.
  }

  private function getClient(): ?Client {
    return $this->client ?? NULL;
  }

  public function setClient(Client $client): void {
    $this->client = $client;
  }

  private function getIdeFromResponse(
    OperationResponse $response,
    \AcquiaCloudApi\Connector\Client $acquiaCloudClient
  ): IdeResponse {
    $cloudApiIdeUrl = $response->links->self->href;
    $urlParts = explode('/', $cloudApiIdeUrl);
    $ideUuid = end($urlParts);
    return (new Ides($acquiaCloudClient))->get($ideUuid);
  }

}
