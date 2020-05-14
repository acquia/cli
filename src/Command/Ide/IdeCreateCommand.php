<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use Exception;
use GuzzleHttp\Client;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class IdeCreateCommand.
 */
class IdeCreateCommand extends CommandBase {

  /**
   * @var \AcquiaCloudApi\Response\IdeResponse
   */
  private $ide;

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Create remote IDE for development')
      ->setName('ide:create');
    // @todo Add option to accept an IDE label.
    // @todo Add option to specify application uuid.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $cloud_application_uuid = $this->determineCloudApplication();
    $checklist = new Checklist($output);

    $question = new Question('<question>Please enter a label for your Remote IDE:</question> ');
    $helper = $this->getHelper('question');
    $ide_label = $helper->ask($input, $output, $question);

    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $ides_resource = new Ides($acquia_cloud_client);

    // Create it.
    $checklist->addItem('Creating your Remote IDE');
    $response = $ides_resource->create($cloud_application_uuid, $ide_label);
    $checklist->completePreviousItem();

    // Get IDE info.
    $checklist->addItem('Getting IDE information');
    $this->ide = $this->getIdeFromResponse($response, $acquia_cloud_client);
    $ide_url = $this->ide->links->ide->href;
    $checklist->completePreviousItem();

    // Wait!
    $this->waitForDnsPropagation($ide_url);

    return 0;
  }

  /**
   * @param $ide_url
   */
  protected function waitForDnsPropagation($ide_url): void {
    if (!$this->getClient()) {
      $this->setClient(new Client(['base_uri' => $ide_url]));
    }

    $loop = Factory::create();
    $spinner = $this->addSpinnerToLoop($loop, 'Waiting for DNS to propagate...');

    $loop->addPeriodicTimer(5, function () use ($loop, $spinner) {
      try {
        $response = $this->client->request('GET', '/health');
        if ($response->getStatusCode() === 200) {
          $this->finishSpinner($spinner);
          $loop->stop();
          $this->output->writeln('');
          $this->output->writeln('<info>Your IDE is ready!</info>');
          $this->output->writeln('');
          $this->output->writeln('<comment>Your IDE URL:</comment> ' . $this->ide->links->ide->href);
          $this->output->writeln('<comment>Your Drupal Site URL:</comment> ' . $this->ide->links->web->href);
          // @todo Prompt to open browser.
        }
      }
      catch (Exception $e) {
        $this->logger->debug($e->getMessage());
      }
    });

    // Add a 15 minute timeout.
    $loop->addTimer(15 * 60, function () use ($loop, $spinner) {
      $this->finishSpinner($spinner);
      $this->output->writeln('<error>Timed out after waiting 15 minutes for DNS to propogate.');
      $this->output->writeln('Either wait longer, or update your local machine to use different DNS servers.');
      // @todo Add docs url.
      $this->output->writeln('@see [docs url]');
      $loop->stop();
    });

    // Start the loop.
    $loop->run();
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
