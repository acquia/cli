<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class IdeCreateCommand.
 */
class IdeCreateCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:create';

  private IdeResponse $ide;

  private Client $client;

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Create a Cloud IDE');
    $this->acceptApplicationUuid();
    $this->addOption('label', NULL, InputOption::VALUE_REQUIRED, 'The label for the IDE');
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $cloud_application_uuid = $this->determineCloudApplication();
    $checklist = new Checklist($output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_resource = new Account($acquia_cloud_client);
    $account = $account_resource->get();
    $default = "$account->first_name $account->last_name's IDE";
    $ide_label = $this->determineOption('label', $input, FALSE, \Closure::fromCallable([$this, 'validateIdeLabel']), $default);

    // Create it.
    $checklist->addItem('Creating your Cloud IDE');
    $ides_resource = new Ides($acquia_cloud_client);
    $response = $ides_resource->create($cloud_application_uuid, $ide_label);
    $checklist->completePreviousItem();

    // Get IDE info.
    $checklist->addItem('Getting IDE information');
    $this->ide = $this->getIdeFromResponse($response, $acquia_cloud_client);
    $ide_url = $this->ide->links->ide->href;
    $checklist->completePreviousItem();

    // Wait!
    return $this->waitForDnsPropagation($ide_url);
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

  /**
   * @param $ide_url
   */
  private function waitForDnsPropagation($ide_url): int {
    $ideCreated = FALSE;
    if (!$this->getClient()) {
      $this->setClient(new Client(['base_uri' => $ide_url]));
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

    return 0;
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
    \AcquiaCloudApi\Connector\Client $acquia_cloud_client
  ): IdeResponse {
    $cloud_api_ide_url = $response->links->self->href;
    $url_parts = explode('/', $cloud_api_ide_url);
    $ide_uuid = end($url_parts);
    return (new Ides($acquia_cloud_client))->get($ide_uuid);
  }

}
