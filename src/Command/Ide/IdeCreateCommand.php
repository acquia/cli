<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use AlecRabbit\Snake\Spinner;
use GuzzleHttp\Client;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class IdeCreateCommand
 */
class IdeCreateCommand extends CommandBase
{

    use ExecTrait;
    /**
     * @var \AcquiaCloudApi\Response\IdeResponse
     */
    private $ide;
    /**
     * @var \Symfony\Component\Console\Output\ConsoleSectionOutput
     */
    private $section;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ide:create')
          ->setDescription('Create remote IDE for development');
        // @todo Add option to accept an IDE label.
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cloud_application_uuid = $this->determineCloudApplication();
        $this->section = $output->section();

        $question = new Question('<question>Please enter a label for your Remote IDE:</question> ');
        $helper = $this->getHelper('question');
        $ide_label = $helper->ask($input, $output, $question);

        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $ides_resource = new Ides($acquia_cloud_client);

        // Create it.
        $this->section->writeln('Creating your Remote IDE... ');
        $response = $ides_resource->create($cloud_application_uuid, $ide_label);
        $this->section->clear(1);
        $this->section->writeln('<info>✔</info> Creating your Remote IDE');

        // Get IDE info.
        $this->section->writeln('Getting IDE information... ');
        $this->ide = $this->getIdeFromResponse($response, $acquia_cloud_client);
        $ide_url = $this->ide->links->ide->href;
        $this->section->clear(1);
        $this->section->writeln('<info>✔</info> Getting IDE information');

        // Wait!
        $this->waitForDnsPropagation($ide_url);

        return 0;
    }

    /**
     * @param $ide_url
     */
    protected function waitForDnsPropagation($ide_url): void
    {
        $loop = Factory::create();
        $spinner = new Spinner();
        $loop->addPeriodicTimer($spinner->interval(), static function () use ($spinner) {
            $spinner->spin();
        });

        $loop->addPeriodicTimer(5, function () use ($loop, $ide_url) {
            $client = new Client(['base_uri' => $ide_url]);
            try {
                $response = $client->request('GET', '/health');
                if ($response->getStatusCode() === 200) {
                    $loop->stop();
                    $this->section->writeln('<info>Your IDE is ready!</info>');
                    $this->section->writeln('');
                    $this->section->writeln('<comment>Your IDE URL:</comment> ' . $this->ide->links->ide->href);
                    $this->section->writeln('<comment>Your Drupal Site URL:</comment> ' . $this->ide->links->web->href);
                    // @todo Prompt to open browser.
                }
            }
            catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
        });

        // Add a 15 minute timeout.
        $loop->addTimer(15 * 60, function () use ($loop) {
            $this->output->writeln("<error>Timed out after waiting 15 minutes for DNS to propogate.");
            $this->output->writeln("Either wait longer, or update your local machine to use different DNS servers.");
            $this->output->writeln("@see [docs url]");
            $loop->stop();
        });

        // Start the loop and spinner.
        $this->section->writeln('Waiting for DNS to propagate... ');
        $spinner->begin();
        $loop->run();
        $spinner->end();
        $this->section->clear(1);
        $this->section->writeln('<info>✔</info> Waiting for DNS to propagate');
    }

    /**
     * @param \AcquiaCloudApi\Response\OperationResponse $response
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     *
     * @return \AcquiaCloudApi\Response\IdeResponse
     */
    protected function getIdeFromResponse(
      \AcquiaCloudApi\Response\OperationResponse $response,
      \AcquiaCloudApi\Connector\Client $acquia_cloud_client
    ): \AcquiaCloudApi\Response\IdeResponse {
        $cloud_api_ide_url = $response->links->self->href;
        $url_parts = explode('/', $cloud_api_ide_url);
        $ide_uuid = end($url_parts);
        $ides_resource = new Ides($acquia_cloud_client);
        $ide = $ides_resource->get($ide_uuid);

        return $ide;
}
}
