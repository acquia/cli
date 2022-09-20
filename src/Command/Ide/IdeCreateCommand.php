<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use Closure;
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

  /**
   * @var \AcquiaCloudApi\Response\IdeResponse
   */
  private IdeResponse $ide;

  /**
   * @var \GuzzleHttp\Client
   */
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $cloud_application_uuid = $this->determineCloudApplication();
    $checklist = new Checklist($output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_resource = new Account($acquia_cloud_client);
    $account = $account_resource->get();
    $default = "{$account->first_name} {$account->last_name}'s IDE";
    if ($input->getOption('label')) {
      $ide_label = $input->getOption('label');
      $this->validateIdeLabel($ide_label);
    }
    else {
      $ide_label = $this->io->ask("Please enter a label for your Cloud IDE. Press enter to use default", $default, Closure::fromCallable([$this, 'validateIdeLabel']));
    }

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
   * @param string $label
   *
   * @return string
   */
  private function validateIdeLabel(string $label): string {
    $violations = Validation::createValidator()->validate($label, [
      new Regex(['pattern' => '/^[\w\' ]+$/', 'message' => 'Please use only letters, numbers, and spaces']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    return $label;
  }

  /**
   * @param $ide_url
   *
   * @return int
   * @infection-ignore-all
   */
  protected function waitForDnsPropagation($ide_url): int {
    if (!$this->getClient()) {
      $this->setClient(new Client(['base_uri' => $ide_url]));
    }
    $checkIdeStatus = function () use (&$response) {
      $response = $this->client->request('GET', '/health');
      return $response->getStatusCode() === 200;
    };
    $doneCallback = function () use (&$response) {
      if ($response->getStatusCode() === 200) {
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

  /**
   * @return \GuzzleHttp\Client|null
   */
  private function getClient(): ?Client {
    return $this->client;
  }

  /**
   * @param \GuzzleHttp\Client $client
   */
  public function setClient(Client $client): void {
    $this->client = $client;
  }

  /**
   * @param \AcquiaCloudApi\Response\OperationResponse $response
   * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
   *
   * @return \AcquiaCloudApi\Response\IdeResponse
   */
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
