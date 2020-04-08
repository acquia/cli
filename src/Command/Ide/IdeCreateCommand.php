<?php

namespace Acquia\Ads\Command\Ide;

use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use Symfony\Component\Console\Question\Question;

/**
 * Class CreateProjectCommand
 *
 * @package Grasmash\YamlCli\Command
 */
class IdeCreateCommand extends CommandBase
{

    use ExecTrait;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ide:create')
          ->setDescription('');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @todo Automatically detect current applications, else:
        $datastore = $this->getApplication()->getDatastore();
        $cloud_api_conf = $datastore->get('cloud_api.conf');
        $config = [
          'key' => $cloud_api_conf['key'],
          'secret' => $cloud_api_conf['secret'],
        ];
        $connector = new Connector($config);
        $acquia_cloud_client = Client::factory($connector);
        $applications_resource = new Applications($acquia_cloud_client);

        // Get all applications.
        $customer_applications = $applications_resource->getAll();
        $application_list = [];
        foreach ($customer_applications as $customer_application) {
            $application_list[$customer_application->uuid] = $customer_application->name;
        }
        asort($application_list);
        $application_names = array_values($application_list);
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
          'Please select the application for which you\'d like to create a new IDE', $application_names
        );
        $choice_id = $helper->ask($input, $output, $question);
        $application_uuid = array_search($choice_id, $application_list);

        // Create the IDE!
        $ides_resource = new Ides($acquia_cloud_client);
        $question = new Question('Please enter a label for your Remote IDE:');
        $ide_label = $helper->ask($input, $output, $question);
        $ides_resource->create($application_uuid, $ide_label);

        return 0;
    }
}
