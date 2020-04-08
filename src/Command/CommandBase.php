<?php

namespace Acquia\Ads\Command;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use Dflydev\DotAccessData\Data;
use Grasmash\YamlCli\Loader\JsonFileLoader;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CommandBase
 *
 * @package Grasmash\YamlCli\Command
 */
abstract class CommandBase extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Filesystem */
    protected $fs;
    /**
     * @var InputInterface
     */
    protected $input;
    /**
     * @var OutputInterface
     */
    protected $output;

    /** @var FormatterHelper */
    protected $formatter;

    /**
     * Initializes the command just after the input has been validated.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = $this->getHelper('formatter');
        $this->fs = new Filesystem();
        $this->setLogger(new ConsoleLogger($output));
    }

    /**
     * @return \AcquiaCloudApi\Connector\Client
     */
    protected function getAcquiaCloudClient(): Client
    {
        // @todo Automatically detect current applications, else:
        /** @var \Acquia\Ads\AdsApplication $application */
        $application = $this->getApplication();
        $datastore = $application->getDatastore();
        $cloud_api_conf = $datastore->get('cloud_api.conf');
        $config = [
          'key' => $cloud_api_conf['key'],
          'secret' => $cloud_api_conf['secret'],
        ];
        $connector = new Connector($config);

        return Client::factory($connector);
    }

    /**
     * Get a list of customer applications suitable for display as CLI choice.
     *
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     *
     * @return array
     */
    protected function getApplicationList(Client $acquia_cloud_client): array
    {
        $applications_resource = new Applications($acquia_cloud_client);

        // Get all applications.
        $customer_applications = $applications_resource->getAll();
        $application_list = [];
        foreach ($customer_applications as $customer_application) {
            $application_list[$customer_application->uuid] = $customer_application->name;
        }
        asort($application_list);

        return $application_list;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     *
     * @return string
     */
    protected function promptChooseApplication(
      InputInterface $input,
      OutputInterface $output,
      Client $acquia_cloud_client
    ): string {
        $application_list = $this->getApplicationList($acquia_cloud_client);
        $application_names = array_values($application_list);
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Please select the application for which you\'d like to create a new IDE',
          $application_names);
        $choice_id = $helper->ask($input, $output, $question);
        $application_uuid = array_search($choice_id, $application_list, true);

        return $application_uuid;
    }
}
