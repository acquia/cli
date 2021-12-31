<?php

namespace Acquia\Cli\Helpers;

use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\DataStore\YamlStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Endpoints\Logs;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\EnvironmentsResponse;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Class LocalMachineHelper.
 *
 * A helper for executing commands on the local client. A wrapper for 'exec' and 'passthru'.
 *
 * @package Acquia\Cli\Helpers
 */
class CloudProxyHelper {

  /**
   * @var \Acquia\Cli\Helpers\SshHelper
   */
  private SshHelper $sshHelper;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  private InputInterface $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  /**
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  private SymfonyStyle $io;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * @var \Acquia\Cli\DataStore\YamlStore
   */
  private YamlStore $datastoreAcli;

  /**
   * @var string
   */
  private string $repoRoot;

  /**
   * @var \Acquia\Cli\CloudApi\ClientService
   */
  private ClientService $cloudApiClientService;

  /**
   * @param \Acquia\Cli\Helpers\SshHelper $ssh_helper
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Acquia\Cli\DataStore\YamlStore $datastore_acli
   * @param string $repoRoot
   */
  public function __construct(
    SshHelper $ssh_helper,
    InputInterface $input,
    OutputInterface $output,
    LoggerInterface $logger,
    YamlStore $datastore_acli,
    string $repoRoot,
    ClientService $cloud_api_client_service
  ) {
    $this->sshHelper = $ssh_helper;
    $this->input = $input;
    $this->output = $output;
    $this->io = new SymfonyStyle($input, $output);
    $this->logger = $logger;
    $this->datastoreAcli = $datastore_acli;
    $this->repoRoot = $repoRoot;
    $this->cloudApiClientService = $cloud_api_client_service;
  }

  /**
   * @param string $ssh_url
   *   The SSH URL to the server.
   *
   * @return string
   *   The sitegroup. E.g., eemgrasmick.
   */
  public static function getSiteGroupFromSshUrl(string $ssh_url): string {
    $ssh_url_parts = explode('.', $ssh_url);
    $sitegroup = reset($ssh_url_parts);

    return $sitegroup;
  }

  /**
   * @param $cloud_environment
   *
   * @return bool
   */
  protected function isAcsfEnv($cloud_environment): bool {
    if (strpos($cloud_environment->sshUrl, 'enterprise-g1') !== FALSE) {
      return TRUE;
    }
    foreach ($cloud_environment->domains as $domain) {
      if (strpos($domain, 'acsitefactory') !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws AcquiaCliException
   */
  protected function getAcsfSites(EnvironmentResponse $cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment->sshUrl);
    $command = ['cat', "/var/www/site-php/$sitegroup.{$cloud_environment->name}/multisite-config.json"];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }
    throw new AcquiaCliException("Could not get ACSF sites");
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return array
   * @throws AcquiaCliException
   */
  protected function getCloudSites(EnvironmentResponse $cloud_environment): array {
    $sitegroup = self::getSiteGroupFromSshUrl($cloud_environment->sshUrl);
    $command = ['ls', $this->getCloudSitesPath($cloud_environment, $sitegroup)];
    $process = $this->sshHelper->executeCommand($cloud_environment, $command, FALSE);
    $sites = array_filter(explode("\n", trim($process->getOutput())));
    if ($process->isSuccessful() && $sites) {
      return $sites;
    }

    throw new AcquiaCliException("Could not get Cloud sites for " . $cloud_environment->name);
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   * @param string $sitegroup
   *
   * @return string
   */
  protected function getCloudSitesPath(EnvironmentResponse $cloud_environment, string $sitegroup): string {
    if ($cloud_environment->platform === 'cloud-next') {
      $path = "/home/clouduser/{$cloud_environment->name}/sites";
    }
    else {
      $path = "/mnt/files/$sitegroup.{$cloud_environment->name}/sites";
    }
    return $path;
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws AcquiaCliException
   */
  protected function promptChooseAcsfSite(EnvironmentResponse $cloud_environment) {
    $choices = [];
    $acsf_sites = $this->getAcsfSites($cloud_environment);
    foreach ($acsf_sites['sites'] as $domain => $acsf_site) {
      $choices[] = "{$acsf_site['name']} ($domain)";
    }
    $choice = $this->io->choice('Choose a site', $choices, $choices[0]);
    $key = array_search($choice, $choices, TRUE);
    $sites = array_values($acsf_sites['sites']);
    $site = $sites[$key];

    return $site['name'];
  }

  /**
   * @param EnvironmentResponse $cloud_environment
   *
   * @return mixed
   * @throws AcquiaCliException
   */
  protected function promptChooseCloudSite(EnvironmentResponse $cloud_environment) {
    $sites = $this->getCloudSites($cloud_environment);
    if (count($sites) === 1) {
      $site = reset($sites);
      $this->logger->debug("Only a single Cloud site was detected. Assuming site is $site");
      return $site;
    }
    $this->logger->debug("Multisite detected");
    $this->warnMultisite();
    return $this->io->choice('Choose a site', $sites, $sites[0]);
  }

  /**
   * Prompts the user to choose from a list of available Cloud Platform
   * applications.
   *
   * @param Client $acquia_cloud_client
   *
   * @return null|object|array
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptChooseApplication(
    Client $acquia_cloud_client
  ) {
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();

    if (!$customer_applications->count()) {
      throw new AcquiaCliException("You have no Cloud applications.");
    }
    return $this->promptChooseFromObjectsOrArrays(
      $customer_applications,
      'uuid',
      'name',
      'Please select a Cloud Platform application:'
    );
  }

  /**
   * Prompts the user to choose from a list of environments for a given Cloud Platform application.
   *
   * @param Client $acquia_cloud_client
   * @param string $application_uuid
   *
   * @return null|object|array
   */
  protected function promptChooseEnvironment(
    Client $acquia_cloud_client,
    string $application_uuid
  ) {
    $environment_resource = new Environments($acquia_cloud_client);
    $environments = $environment_resource->getAll($application_uuid);
    // @todo Make sure there are actually environments here.
    return $this->promptChooseFromObjectsOrArrays(
      $environments,
      'uuid',
      'name',
      'Please select a Cloud Platform environment:'
    );
  }

  /**
   * Prompts the user to choose from a list of logs for a given Cloud Platform environment.
   *
   * @param Client $acquia_cloud_client
   * @param string $environment_id
   *
   * @return null|object|array
   */
  protected function promptChooseLogs(
    Client $acquia_cloud_client,
    string $environment_id
  ) {
    $logs_resource = new Logs($acquia_cloud_client);
    $logs = $logs_resource->getAll($environment_id);

    return $this->promptChooseFromObjectsOrArrays(
      $logs,
      'type',
      'label',
      'Please select one or more logs as a comma-separated list:',
      TRUE
    );
  }

  /**
   * Prompt a user to choose from a list.
   *
   * The list is generated from an array of objects. The objects much have at least one unique property and one
   * property that can be used as a human readable label.
   *
   * @param object[]|array[] $items An array of objects or arrays.
   * @param string $unique_property The property of the $item that will be used to identify the object.
   * @param string $label_property
   * @param string $question_text
   *
   * @param bool $multiselect
   *
   * @return null|array|object
   */
  public function promptChooseFromObjectsOrArrays($items, string $unique_property, string $label_property, string $question_text, $multiselect = FALSE) {
    $list = [];
    foreach ($items as $item) {
      if (is_array($item)) {
        $list[$item[$unique_property]] = trim($item[$label_property]);
      }
      else {
        $list[$item->$unique_property] = trim($item->$label_property);
      }
    }
    $labels = array_values($list);
    $default = $multiselect ? NULL : $labels[0];
    $question = new ChoiceQuestion($question_text, $labels, $default);
    $question->setMultiselect($multiselect);
    $choice_id = $this->io->askQuestion($question);
    if (!$multiselect) {
      $identifier = array_search($choice_id, $list, TRUE);
      foreach ($items as $item) {
        if (is_array($item)) {
          if ($item[$unique_property] === $identifier) {
            return $item;
          }
        }
        else {
          if ($item->$unique_property === $identifier) {
            return $item;
          }
        }
      }
    }
    else {
      $chosen = [];
      foreach ($choice_id as $choice) {
        $identifier = array_search($choice, $list, TRUE);
        foreach ($items as $item) {
          if (is_array($item)) {
            if ($item[$unique_property] === $identifier) {
              $chosen[] = $item;
            }
          }
          else {
            if ($item->$unique_property === $identifier) {
              $chosen[] = $item;
            }
          }
        }
      }
      return $chosen;
    }

    return NULL;
  }

  /**
   * Gets an array of git remotes from a .git/config array.
   *
   * @param array $git_config
   *
   * @return array
   *   A flat array of git remote urls.
   */
  protected function getGitRemotes(array $git_config): array {
    $local_vcs_remotes = [];
    foreach ($git_config as $section_name => $section) {
      if ((strpos($section_name, 'remote ') !== FALSE) &&
        (strpos($section['url'], 'acquia.com') || strpos($section['url'], 'acquia-sites.com'))
      ) {
        $local_vcs_remotes[] = $section['url'];
      }
    }

    return $local_vcs_remotes;
  }

  /**
   * @param Client $acquia_cloud_client
   * @param array $local_git_remotes
   *
   * @return ApplicationResponse|null
   */
  protected function findCloudApplicationByGitUrl(
    Client $acquia_cloud_client,
    array $local_git_remotes
  ): ?ApplicationResponse {

    // Set up API resources.
    $applications_resource = new Applications($acquia_cloud_client);
    $customer_applications = $applications_resource->getAll();
    $environments_resource = new Environments($acquia_cloud_client);

    // Create progress bar.
    $count = count($customer_applications);
    $progressBar = new ProgressBar($this->output, $count);
    $progressBar->setFormat('message');
    $progressBar->setMessage("Searching <options=bold>$count applications</> on the Cloud Platform...");
    $progressBar->start();

    // Search Cloud applications.
    $terminal_width = (new Terminal())->getWidth();
    foreach ($customer_applications as $application) {
      // Ensure that the message takes up the full terminal width to prevent display artifacts.
      $message = "Searching <options=bold>{$application->name}</> for matching git URLs";
      $suffix_length = $terminal_width - strlen($message) - 17;
      $suffix = $suffix_length > 0 ? str_repeat(' ', $suffix_length) : '';
      $progressBar->setMessage($message . $suffix);
      $application_environments = $environments_resource->getAll($application->uuid);
      if ($application = $this->searchApplicationEnvironmentsForGitUrl(
        $application,
        $application_environments,
        $local_git_remotes
      )) {
        $progressBar->finish();
        $progressBar->clear();

        return $application;
      }
      $progressBar->advance();
    }
    $progressBar->finish();
    $progressBar->clear();

    return NULL;
  }

  /**
   * @param ApplicationResponse $application
   * @param \AcquiaCloudApi\Response\EnvironmentsResponse $application_environments
   * @param array $local_git_remotes
   *
   * @return ApplicationResponse|null
   */
  protected function searchApplicationEnvironmentsForGitUrl(
    ApplicationResponse $application,
    EnvironmentsResponse $application_environments,
    array $local_git_remotes
  ): ?ApplicationResponse {
    foreach ($application_environments as $environment) {
      if ($environment->flags->production && in_array($environment->vcs->url, $local_git_remotes, TRUE)) {
        $this->logger->debug("Found matching Cloud application! {$application->name} with uuid {$application->uuid} matches local git URL {$environment->vcs->url}");

        return $application;
      }
    }

    return NULL;
  }

  /**
   * Infer which Cloud Platform application is associated with the current local git repository.
   *
   * If the local git repository has a remote with a URL that matches a Cloud Platform application's VCS URL, assume
   * that we have a match.
   *
   * @param Client $acquia_cloud_client
   *
   * @return ApplicationResponse|null
   */
  protected function inferCloudAppFromLocalGitConfig(
    Client $acquia_cloud_client
  ): ?ApplicationResponse {
    if ($this->repoRoot && $this->input->isInteractive()) {
      $this->output->writeln("There is no Cloud Platform application linked to <options=bold>{$this->repoRoot}/.git</>.");
      $answer = $this->io->confirm('Would you like Acquia CLI to search for a Cloud application that matches your local git config?');
      if ($answer) {
        $this->output->writeln('Searching for a matching Cloud application...');
        if ($git_config = $this->getGitConfig()) {
          $local_git_remotes = $this->getGitRemotes($git_config);
          if ($cloud_application = $this->findCloudApplicationByGitUrl($acquia_cloud_client,
            $local_git_remotes)) {
            $this->output->writeln('<info>Found a matching application!</info>');
            return $cloud_application;
          }
          else {
            $this->output->writeln('<comment>Could not find a matching Cloud application.</comment>');
            return NULL;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Determine the Cloud environment.
   *
   * @return mixed
   * @throws \Exception
   */
  protected function determineCloudEnvironment() {
    if ($this->input->hasArgument('environmentId') && $this->input->getArgument('environmentId')) {
      return $this->input->getArgument('environmentId');
    }

    if (!$this->input->isInteractive()) {
      throw new RuntimeException('Not enough arguments (missing: "environmentId").');
    }

    $application_uuid = $this->determineCloudApplication();
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $environment = $this->promptChooseEnvironment($acquia_cloud_client, $application_uuid);

    return $environment->uuid;
  }

  /**
   * Determine the Cloud application.
   *
   * @param bool $prompt_link_app
   *
   * @return string|null
   * @throws \Exception
   */
  public function determineCloudApplication(bool $prompt_link_app): ?string {
    $application_uuid = $this->doDetermineCloudApplication();
    if (!isset($application_uuid)) {
      throw new AcquiaCliException("Could not determine Cloud Application. Run this command interactively or use `acli link` to link a Cloud Application before running non-interactively.");
    }

    $application = $this->getCloudApplication($application_uuid);
    // No point in trying to link a directory that's not a repo.
    if (!empty($this->repoRoot) && !$this->getCloudUuidFromDatastore()) {
      if ($prompt_link_app) {
        $this->saveCloudUuidToDatastore($application);
      }
      elseif (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->getCloudApplicationUuidFromBltYaml()) {
        $this->promptLinkApplication($application);
      }
    }

    return $application_uuid;
  }

  /**
   * @return array|false|mixed|string|null
   * @throws \Exception
   */
  protected function doDetermineCloudApplication() {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();

    if ($this->input->hasArgument('applicationUuid') && $this->input->getArgument('applicationUuid')) {
      $cloud_application_uuid = $this->input->getArgument('applicationUuid');
      return CommandBase::validateUuid($cloud_application_uuid);
    }

    // Try local project info.
    if ($application_uuid = $this->getCloudUuidFromDatastore()) {
      $this->logger->debug("Using Cloud application UUID: $application_uuid from .acquia-cli.yml");
      return $application_uuid;
    }

    if ($application_uuid = $this->getCloudApplicationUuidFromBltYaml()) {
      $this->logger->debug("Using Cloud application UUID $application_uuid from blt/blt.yml");
      return $application_uuid;
    }

    // Get from the Cloud Platform env var.
    if ($application_uuid = IdeHelper::getThisCloudIdeCloudAppUuid()) {
      return $application_uuid;
    }

    // Try to guess based on local git url config.
    if ($cloud_application = $this->inferCloudAppFromLocalGitConfig($acquia_cloud_client)) {
      return $cloud_application->uuid;
    }

    // Finally, just ask.
    if ($this->input->isInteractive() && $application = $this->promptChooseApplication($acquia_cloud_client)) {
      return $application->uuid;
    }

    return NULL;
  }

  /**
   * @return string|null
   */
  protected function getCloudApplicationUuidFromBltYaml(): ?string {
    $blt_yaml_file_path = Path::join($this->repoRoot, 'blt', 'blt.yml');
    if (file_exists($blt_yaml_file_path)) {
      $contents = Yaml::parseFile($blt_yaml_file_path);
      if (array_key_exists('cloud', $contents) && array_key_exists('appId', $contents['cloud'])) {
        return $contents['cloud']['appId'];
      }
    }

    return NULL;
  }

  /**
   * @param string $application_uuid
   *
   * @return ApplicationResponse
   */
  protected function getCloudApplication(string $application_uuid): ApplicationResponse {
    $applications_resource = new Applications($this->cloudApiClientService->getClient());
    return $applications_resource->get($application_uuid);
  }

  /**
   * @param string $environment_id
   *
   * @return EnvironmentResponse
   * @throws \Exception
   */
  protected function getCloudEnvironment(string $environment_id): EnvironmentResponse {
    $environment_resource = new Environments($this->cloudApiClientService->getClient());

    return $environment_resource->get($environment_id);
  }

  /**
   * @param string $ide_uuid
   *
   * @return \stdClass|null
   */
  protected function findIdeSshKeyOnCloud(string $ide_uuid): ?stdClass {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($ide_uuid);
    $ssh_key_label = SshKeyCommandBase::getIdeSshKeyLabel($ide);
    foreach ($cloud_keys as $cloud_key) {
      if ($cloud_key->label === $ssh_key_label) {
        return $cloud_key;
      }
    }
    return NULL;
  }

  /**
   * @param \stdClass|null $cloud_key
   *
   * @throws AcquiaCliException
   * @throws \Exception
   */
  protected function deleteSshKeyFromCloud(stdClass $cloud_key): void {
    $return_code = $this->executeAcliCommand('ssh-key:delete', [
      '--cloud-key-uuid' => $cloud_key->uuid,
    ]);
    if ($return_code !== 0) {
      throw new AcquiaCliException('Unable to delete SSH key from the Cloud Platform');
    }
  }

  /**
   * Load configuration from .git/config.
   *
   * @return array|null
   */
  protected function getGitConfig(): ?array {
    $file_path = $this->repoRoot . '/.git/config';
    if (file_exists($file_path)) {
      return parse_ini_file($file_path, TRUE);
    }

    return NULL;
  }

  /**
   * @param ApplicationResponse|null $cloud_application
   *
   * @return bool
   * @throws \Exception
   */
  protected function promptLinkApplication(
    ?ApplicationResponse $cloud_application
  ): bool {
    $answer = $this->io->confirm("Would you like to link the Cloud application <bg=cyan;options=bold>{$cloud_application->name}</> to this repository?");
    if ($answer) {
      return $this->saveCloudUuidToDatastore($cloud_application);
    }
    return FALSE;
  }

  /**
   * @param ApplicationResponse $application
   *
   * @return bool
   * @throws \Exception
   */
  protected function saveCloudUuidToDatastore(ApplicationResponse $application): bool {
    $this->datastoreAcli->set('cloud_app_uuid', $application->uuid);
    $this->io->success("The Cloud application {$application->name} has been linked to this repository by writing to .acquia-cli.yml in the repository root.");

    return TRUE;
  }

  /**
   * @return mixed
   */
  protected function getCloudUuidFromDatastore() {
    return $this->datastoreAcli->get('cloud_app_uuid');
  }

  /**
   *
   */
  protected function warnMultisite(): void {
    $this->io->note("This is a multisite application. Drupal will load the default site unless you've configured sites.php for this environment: https://docs.acquia.com/cloud-platform/develop/drupal/multisite/");
  }

}
