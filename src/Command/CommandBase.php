<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\AdsApplication;
use Acquia\Ads\Connector\AdsCloudConnector;
use Acquia\Ads\Helpers\LocalMachineHelper;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\ApplicationsResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @var \Acquia\Ads\DataStore\DataStoreInterface
     */
    private $datastore;

    private $cloudApplication;

    private $localProjectInfo;

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

        /** @var \Acquia\Ads\AdsApplication $application */
        $application = $this->getApplication();
        $this->datastore = $application->getDatastore();
    }

    /**
     * Gets the application instance for this command.
     *
     * @return AdsApplication|null An Application instance
     */
    public function getApplication(): ?AdsApplication
    {
        return $this->application;
    }

    /**
     * @return \Acquia\Ads\DataStore\DataStoreInterface
     */
    public function getDatastore(): \Acquia\Ads\DataStore\DataStoreInterface
    {
        return $this->datastore;
    }

    /**
     * @return \AcquiaCloudApi\Connector\Client
     */
    protected function getAcquiaCloudClient(): Client
    {
        $cloud_api_conf = $this->datastore->get('cloud_api.conf');
        $config = [
          'key' => $cloud_api_conf['key'],
          'secret' => $cloud_api_conf['secret'],
        ];
        $connector = new AdsCloudConnector($config);

        return Client::factory($connector);
    }

    /**
     * Get a list of customer applications suitable for display as CLI choice.
     *
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     *
     * @return ApplicationsResponse[]
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
        $question = new ChoiceQuestion(
            'Please select the application for which you\'d like to create a new IDE',
            $application_names
        );
        $choice_id = $helper->ask($input, $output, $question);
        $application_uuid = array_search($choice_id, $application_list, true);

        return $application_uuid;
    }


    /**
     * @param \Acquia\Ads\AdsApplication $application
     */
    protected function loadLocalProjectInfo(AdsApplication $application)
    {
        $local_user_config = $this->getDatastore()->get('ads-cli/user.json');
        // Save empty local project info.
        // @todo Abstract this.
        if ($local_user_config !== null) {
            foreach ($local_user_config['localProjects'] as $project) {
                if ($project['directory'] === $application->getRepoRoot()) {
                    $this->localProjectInfo = $project;
                    return;
                }
            }
        } else {
            $local_user_config = [];
        }

        // @todo Abstract this.
        // Save new project info.
        $project = [];
        $project['name'] = basename($this->getApplication()->getRepoRoot());
        $project['directory'] = $this->getApplication()->getRepoRoot();
        $local_user_config['localProjects'][] = $project;

        $this->localProjectInfo = $local_user_config;
        $this->getDatastore()->set('ads-cli/user.json', $local_user_config);
    }

    /**
     * @param \Acquia\Ads\AdsApplication $application
     *
     * @return array|bool
     */
    protected function getGitConfig(AdsApplication $application)
    {
        $git_config = parse_ini_file($application->getRepoRoot() . '/.git/config', true);

        return $git_config;
    }

    /**
     * @param $git_config
     *
     * @return array
     */
    protected function getGitRemotes($git_config): array
    {
        $local_vcs_remotes = [];
        foreach ($git_config as $section_name => $section) {
            if (strpos($section_name, 'remote ') !== false) {
                if (strpos($section['url'], 'acquia.com')) {
                    $local_vcs_remotes[] = $section['url'];
                }
            }
        }

        return $local_vcs_remotes;
    }

    /**
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     * @param array $local_git_remotes
     *
     * @return \AcquiaCloudApi\Response\ApplicationResponse|null
     */
    protected function findCloudApplicationByGitUrl(
        Client $acquia_cloud_client,
        array $local_git_remotes
    ): ?ApplicationResponse {
        $applications_resource = new Applications($acquia_cloud_client);
        $customer_applications = $applications_resource->getAll();
        $environments_resource = new Environments($acquia_cloud_client);
        foreach ($customer_applications as $application) {
            $this->logger->debug("Searching {$application->name} for git URLs that match local git config.");
            // @todo Add progress bar.
            $application_environments = $environments_resource->getAll($application->uuid);
            foreach ($application_environments as $environment) {
                if ($environment->flags->production && in_array($environment->vcs->url, $local_git_remotes, true)) {
                    $this->logger->debug("Found matching Cloud application! {$application->name} with uuid {$application->uuid} matches local git URL {$environment->vcs->url}");
                    return $application;
                }
            }
        }

        return null;
    }

    /**
     * @param \Acquia\Ads\AdsApplication $application
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     *
     * @return \AcquiaCloudApi\Response\ApplicationResponse|null
     */
    protected function inferCloudAppFromLocalGitConfig(
        AdsApplication $application,
        Client $acquia_cloud_client
    ): ?ApplicationResponse {
        $this->output->writeln("There is no Acquia Cloud application linked to <comment>{$application->getRepoRoot()}/.git</comment>.");
        $question = new ConfirmationQuestion("<question>Would you like ADS to search for a Cloud application that matches your local git config?</question>");
        $helper = $this->getHelper('question');
        $answer = $helper->ask($this->input, $this->output, $question);
        if ($answer) {
            $this->output->writeln("Searching for a matching Cloud application...");
            $git_config = $this->getGitConfig($application);
            $local_git_remotes = $this->getGitRemotes($git_config);
            $cloud_application = $this->findCloudApplicationByGitUrl($acquia_cloud_client, $local_git_remotes);
            return $cloud_application;
        }

        return null;
    }

    /**
     *
     * @return string|null
     */
    protected function determineCloudApplication(): ?string
    {
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        /** @var \Acquia\Ads\AdsApplication $ads_application */
        $ads_application = $this->getApplication();
        $this->loadLocalProjectInfo($ads_application);
        if (isset($this->localProjectInfo) && array_key_exists('cloud_application_uuid', $this->localProjectInfo)) {
            $application_uuid = $this->localProjectInfo['cloud_application_uuid'];
        } elseif ($cloud_application = $this->inferCloudAppFromLocalGitConfig($ads_application, $acquia_cloud_client)) {
            $question = new ConfirmationQuestion("<question>Would you like to link the project at {$ads_application->getRepoRoot()} with the Cloud App \"{$cloud_application->name}\"</question>?");
            $helper = $this->getHelper('question');
            $answer = $helper->ask($this->input, $this->output, $question);
            if ($answer) {
                $this->saveLocalConfigCloudAppUuid($cloud_application->uuid);
            }
            $application_uuid = $cloud_application->uuid;
        } else {
            $application_uuid = $this->promptChooseApplication($this->input, $this->output, $acquia_cloud_client);
            $this->saveLocalConfigCloudAppUuid($application_uuid);
        }

        return $application_uuid;
    }

    /**
     * @param string $application_uuid
     */
    protected function saveLocalConfigCloudAppUuid($application_uuid): void
    {
        $local_user_config = $this->getDatastore()->get('ads-cli/user.json');
        foreach ($local_user_config['localProjects'] as $key => $project) {
            if ($project['directory'] === $this->getApplication()->getRepoRoot()) {
                $project['cloud_application_uuid'] = $application_uuid;

                $local_user_config['localProjects'][$key] = $project;
                $this->localProjectInfo = $local_user_config;
                $this->getDatastore()->set('ads-cli/user.json', $local_user_config);
                return;
            }
        }
    }
}
