<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use Exception;
use GuzzleHttp\Client;
use React\EventLoop\Loop;
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
  private $ide;

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  /**
   * @var \Acquia\Cli\Output\Checklist
   */
  private $checklist;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Create a Cloud IDE for development');
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
  protected function execute(InputInterface $input, OutputInterface $output) {
    $cloud_application_uuid = $this->determineCloudApplication();
    $this->checklist = new Checklist($output);
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $account_resource = new Account($acquia_cloud_client);
    $account = $account_resource->get();
    $default = "{$account->first_name} {$account->last_name}'s IDE";
    if ($input->getOption('label')) {
      $ide_label = $input->getOption('label');
      $this->validateIdeLabel($ide_label);
    }
    else {
      $ide_label = $this->io->ask("Please enter a label for your Cloud IDE. Press enter to use default", $default, \Closure::fromCallable([$this, 'validateIdeLabel']));
    }

    // Create it.
    $this->checklist->addItem('Creating your Cloud IDE');
    $ides_resource = new Ides($acquia_cloud_client);
    $response = $ides_resource->create($cloud_application_uuid, $ide_label);
    $this->checklist->completePreviousItem();

    // Get IDE info.
    $this->checklist->addItem('Getting IDE information');
    $this->ide = $this->getIdeFromResponse($response, $acquia_cloud_client);
    $ide_url = $this->ide->links->ide->href;
    $this->checklist->completePreviousItem();

    // Wait!
    return $this->waitForDnsPropagation($ide_url);
  }

  /**
   * @param string $label
   *
   * @return string
   */
  protected function validateIdeLabel(string $label): string {
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
   */
  protected function waitForDnsPropagation($ide_url): int {
    if (!$this->getClient()) {
      $this->setClient(new Client(['base_uri' => $ide_url]));
    }

    $loop = Loop::get();
    $spinner = LoopHelper::addSpinnerToLoop($loop, 'Waiting for the IDE to be ready. This can take up to 15 minutes...', $this->output);

    $loop->addPeriodicTimer(5, function () use ($loop, $spinner) {
      try {
        $response = $this->client->request('GET', '/health');
        if ($response->getStatusCode() === 200) {
          LoopHelper::finishSpinner($spinner);
          $loop->stop();
          $this->output->writeln('');
          $this->output->writeln('<info>Your IDE is ready!</info>');
        }
      }
      catch (Exception $e) {
        $this->logger->debug($e->getMessage());
      }
    });
    LoopHelper::addTimeoutToLoop($loop, 45, $spinner, $this->output);

    // Start the loop.
    try {
      $loop->run();
    }
    catch (AcquiaCliException $exception) {
      $this->io->error($exception->getMessage());
      // Write IDE links to screen in the event of a DNS timeout. The IDE may still provision correctly.
      $this->writeIdeLinksToScreen();
      return 1;
    }

    $this->writeIdeLinksToScreen();
    return 0;
  }

  /**
   * Writes the IDE links to screen.
   */
  public function writeIdeLinksToScreen(): void {
    $this->output->writeln('');
    $this->output->writeln("<comment>Your IDE URL:</comment> <href={$this->ide->links->ide->href}>{$this->ide->links->ide->href}</>");
    $this->output->writeln("<comment>Your Drupal Site URL:</comment> <href={$this->ide->links->web->href}>{$this->ide->links->web->href}</>");
    // @todo Prompt to open browser.
  }

  /**
   * @return \GuzzleHttp\Client|null
   */
  public function getClient(): ?Client {
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
  protected function getIdeFromResponse(
    OperationResponse $response,
    \AcquiaCloudApi\Connector\Client $acquia_cloud_client
  ): IdeResponse {
    $cloud_api_ide_url = $response->links->self->href;
    $url_parts = explode('/', $cloud_api_ide_url);
    $ide_uuid = end($url_parts);
    $ides_resource = new Ides($acquia_cloud_client);

    return $ides_resource->get($ide_uuid);
  }

}
