<?php

namespace Acquia\Cli\Command\CodeStudio;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Endpoints\Account;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class CodeStudioPipelinesMigrateCommand extends CommandBase {

  use CodeStudioCommandTrait;

  protected static $defaultName = 'codestudio:pipelines-migrate';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Migrate .acquia-pipeline.yml file to .gitlab-ci.yml file for a given Acquia Cloud application')
      ->addOption('key', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API token that Code Studio will use')
      ->addOption('secret', NULL, InputOption::VALUE_REQUIRED, 'The Cloud Platform API secret that Code Studio will use')
      ->addOption('gitlab-token', NULL, InputOption::VALUE_REQUIRED, 'The GitLab personal access token that will be used to communicate with the GitLab instance')
      ->addOption('gitlab-project-id', NULL, InputOption::VALUE_REQUIRED, 'The project ID (an integer) of the GitLab project to configure.')
      ->setAliases(['cs:pipelines-migrate']);
    $this->acceptApplicationUuid();
    $this->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->authenticateWithGitLab();
    $this->writeApiTokenMessage($input);
    $cloudKey = $this->determineApiKey();
    $cloudSecret = $this->determineApiSecret();
    // We may already be authenticated with Acquia Cloud Platform via a refresh token.
    // But, we specifically need an API Token key-pair of Code Studio.
    // So we reauthenticate to be sure we're using the provided credentials.
    $this->reAuthenticate($cloudKey, $cloudSecret, $this->cloudCredentials->getBaseUri(), $this->cloudCredentials->getAccountsUri());
    $cloudApplicationUuid = $this->determineCloudApplication();

    // Get Cloud account.
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $accountAdapter = new Account($acquiaCloudClient);
    $account = $accountAdapter->get();
    $this->setGitLabProjectDescription($cloudApplicationUuid);

    // Get Cloud application.
    $cloudApplication = $this->getCloudApplication($cloudApplicationUuid);
    $project = $this->determineGitLabProject($cloudApplication);

    // Migrate acquia-pipeline file
    $this->checkGitLabCiCdVariables($project);
    $this->validateCwdIsValidDrupalProject();
    $acquiaPipelinesFileDetails = $this->getAcquiaPipelinesFileContents($project);
    $acquiaPipelinesFileContents = $acquiaPipelinesFileDetails['file_contents'];
    $acquiaPipelinesFileName = $acquiaPipelinesFileDetails['filename'];
    $gitlabCiFileContents = $this->getGitLabCiFileTemplate();
    $this->migrateVariablesSection($acquiaPipelinesFileContents, $gitlabCiFileContents);
    $this->migrateEventsSection($acquiaPipelinesFileContents, $gitlabCiFileContents);
    $this->removeEmptyScript($gitlabCiFileContents);
    $this->createGitLabCiFile($gitlabCiFileContents, $acquiaPipelinesFileName);
    $this->io->success([
      "",
      "Migration completed successfully.",
      "Created .gitlab-ci.yml and removed acquia-pipeline.yml file.",
      "In order to run Pipeline, push .gitlab-ci.yaml to Main branch of Code Studio project.",
      "Check your pipeline is running in Code Studio for your project.",
    ]);

    return 0;
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * Check whether wizard command is executed by checking the env variable of codestudio project.
   *
   * @param array $project
   */
  private function checkGitLabCiCdVariables(array $project): void {
    $gitlabCicdVariables = CodeStudioCiCdVariables::getList();
    $gitlabCicdExistingVariables = $this->gitLabClient->projects()->variables($project['id']);
    $existingKeys = array_column($gitlabCicdExistingVariables, 'key');
    foreach ($gitlabCicdVariables as $gitlabCicdVariable) {
      if (!in_array($gitlabCicdVariable, $existingKeys, TRUE)) {
        throw new AcquiaCliException("Code Studio CI/CD variable {$gitlabCicdVariable} is not configured properly");
      }
    }
  }

  /**
   * Check acquia-pipeline.yml file exists in the root repo and remove ci_config_path from codestudio project.
   *
   * @param array $project
   * @return array
   */
  private function getAcquiaPipelinesFileContents(array $project): array {
    $pipelinesFilepathYml = Path::join($this->projectDir, 'acquia-pipelines.yml');
    $pipelinesFilepathYaml = Path::join($this->projectDir, 'acquia-pipelines.yaml');
    if ($this->localMachineHelper->getFilesystem()->exists($pipelinesFilepathYml) ||
      $this->localMachineHelper->getFilesystem()->exists($pipelinesFilepathYaml)
    ) {
      $this->gitLabClient->projects()->update($project['id'], ['ci_config_path' => '']);
      $pipelinesFilenames = ['acquia-pipelines.yml', 'acquia-pipelines.yaml'];
      foreach ($pipelinesFilenames as $pipelinesFilename) {
        $pipelinesFilepath = Path::join($this->projectDir, $pipelinesFilename);
        if (file_exists($pipelinesFilepath)) {
          $fileContents = file_get_contents($pipelinesFilepath);
          return [
            'filename' => $pipelinesFilename,
            'file_contents' => Yaml::parse($fileContents, Yaml::PARSE_OBJECT),
];
        }
      }
    }

    throw new AcquiaCliException("Missing 'acquia-pipelines.yml' file which is required to migrate the project to Code Studio.");
  }

  /**
   * Migrating standard template to .gitlab-ci.yml file.
   *
   * @return array
   */
  private function getGitLabCiFileTemplate(): array {
    return [
        'include' => ['project' => 'acquia/standard-template', 'file' => '/gitlab-ci/Auto-DevOps.acquia.gitlab-ci.yml'],
    ];
  }

  /**
   * Migrating `variables` section to .gitlab-ci.yml file.
   */
  private function migrateVariablesSection($acquiaPipelinesFileContents, &$gitlabCiFileContents): void {
    if (array_key_exists('variables', $acquiaPipelinesFileContents)) {
      $variablesDump = Yaml::dump(['variables' => $acquiaPipelinesFileContents['variables']]);
      $removeGlobal = preg_replace('/global:/', '', $variablesDump);
      $variablesParse = Yaml::parse($removeGlobal);
      $gitlabCiFileContents = array_merge($gitlabCiFileContents, $variablesParse);
      $this->io->success([
        "Migrated `variables` section of acquia-pipelines.yml to .gitlab-ci.yml",
      ]);
    }
    else {
      $this->io->info([
        "Checked acquia-pipeline.yml file for `variables` section",
      ]);
    }
  }

  /**
   * @param array $acquiaPipelinesFileContents
   */
  private function getPipelinesSection(array $acquiaPipelinesFileContents, string $eventName): mixed {
    if (!array_key_exists('events', $acquiaPipelinesFileContents)) {
      return NULL;
    }
    if (array_key_exists('build', $acquiaPipelinesFileContents['events']) && empty($acquiaPipelinesFileContents['events']['build'])) {
      return NULL;
    }
    if (!array_key_exists($eventName, $acquiaPipelinesFileContents['events'])) {
      return NULL;
    }
    return $acquiaPipelinesFileContents['events'][$eventName]['steps'] ?? NULL;
  }

  /**
   * @param array $acquiaPipelinesFileContents
   * @param array $gitlabCiFileContents
   */
  private function migrateEventsSection(array $acquiaPipelinesFileContents, array &$gitlabCiFileContents): void {
    // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
    $eventsMap = [
      'build' => [
        'skip' => [
          'composer install' => [
            'message' => 'Code Studio AutoDevOps will run `composer install` by default. Skipping migration of this command in your acquia-pipelines.yml file:',
            'prompt' => FALSE,
            ],
          '${BLT_DIR}' => [
            'message' => 'Code Studio AutoDevOps will run BLT commands for you by default. Do you want to migrate the following command?',
            'prompt' => TRUE,
          ],
        ],
        'default_stage' => 'Test Drupal',
        'stage' => [
          'setup' => 'Build Drupal',
          'npm run build' => 'Build Drupal',
          'validate' => 'Test Drupal',
          'tests' => 'Test Drupal',
          'test'  => 'Test Drupal',
          'npm test' => 'Test Drupal',
          'artifact' => 'Deploy Drupal',
          'deploy' => 'Deploy Drupal',
        ],
        'needs' => [
          'Build Code',
          'Manage Secrets',
        ],
      ],
      'post-deploy' => [
        'skip' => [
          'launch_ode' => [
            'message' => 'Code Studio AutoDevOps will run Launch a new Continuous Delivery Environment (CDE) automatically for new merge requests. Skipping migration of this command in your acquia-pipelines.yml file:',
            'prompt' => FALSE,
            ],
        ],
        'default_stage' => 'Deploy Drupal',
        'stage' => [
          'launch_ode' => 'Deploy Drupal',
        ],
        'needs' => [
          'Create artifact from branch',
        ],
      ],
    ];
    // phpcs:enable

    $codeStudioJobs = [];
    foreach ($eventsMap as $eventName => $eventMap) {
      $eventSteps = $this->getPipelinesSection($acquiaPipelinesFileContents, $eventName);
      if ($eventSteps) {
        foreach ($eventSteps as $step) {
          $scriptName = array_keys($step)[0];
          if (!array_key_exists('script', $step[$scriptName]) || empty($step[$scriptName]['script'])) {
            continue;
          }
          if ($stage = $this->assignStageFromKeywords($eventMap['stage'], $scriptName)) {
            $codeStudioJobs[$scriptName]['stage'] = $stage;
          }
          foreach ($step[$scriptName]['script'] as $command) {
            foreach ($eventMap['skip'] as $needle => $messageConfig) {
              if (str_contains($command, $needle)) {
                if ($messageConfig['prompt']) {
                  $answer = $this->io->confirm($messageConfig['message'] . PHP_EOL . $command, FALSE);
                  if ($answer == 1) {
                    $codeStudioJobs[$scriptName]['script'][] = $command;
                    $codeStudioJobs[$scriptName]['script'] = array_values(array_unique($codeStudioJobs[$scriptName]['script']));
                  }
                  else if (($key = array_search($command, $codeStudioJobs[$scriptName]['script'], TRUE)) !== FALSE) {
                    unset($codeStudioJobs[$scriptName]['script'][$key]);
                  }
                }
                else {
                  $this->io->note([
                    $messageConfig['message'],
                    $command,
                  ]);
                }
                break;
              }

              if (array_key_exists($scriptName, $codeStudioJobs) && array_key_exists('script', $codeStudioJobs[$scriptName]) && in_array($command, $codeStudioJobs[$scriptName]['script'], TRUE)) {
                break;
              }
              if (!array_key_exists($scriptName, $eventMap['skip']) ) {
                $codeStudioJobs[$scriptName]['script'][] = $command;
                $codeStudioJobs[$scriptName]['script'] = array_values(array_unique($codeStudioJobs[$scriptName]['script']));
              }
              else if ($scriptName === 'launch_ode') {
                $codeStudioJobs[$scriptName]['script'][] = $command;
              }
            }
            if (array_key_exists($scriptName, $codeStudioJobs) && !array_key_exists('stage', $codeStudioJobs[$scriptName])
              && $stage = $this->assignStageFromKeywords($eventMap['stage'], $command)) {
              $codeStudioJobs[$scriptName]['stage'] = $stage;
            }
          }
          if (!array_key_exists('stage', $codeStudioJobs[$scriptName])) {
            $codeStudioJobs[$scriptName]['stage'] = $eventMap['default_stage'];
          }
          $codeStudioJobs[$scriptName]['needs'] = $eventMap['needs'];
        }
        $gitlabCiFileContents = array_merge($gitlabCiFileContents, $codeStudioJobs);
        $this->io->success([
          "Completed migration of the $eventName step in your acquia-pipelines.yml file",
        ]);
      }
      else {
        $this->io->writeln([
          "acquia-pipeline.yml file does not contain $eventName step to migrate",
        ]);
      }
    }

  }

  /**
   * Removing empty script.
   */
  private function removeEmptyScript(array &$gitlabCiFileContents): void {
    foreach ($gitlabCiFileContents as $key => $value) {
      if (array_key_exists('script', $value) && empty($value['script'])) {
        unset($gitlabCiFileContents[$key]);
      }
    }
  }

  /**
   * Creating .gitlab-ci.yml file.
   */
  private function createGitLabCiFile(array $contents,$acquiaPipelinesFileName): void {
    $gitlabCiFilepath = Path::join($this->projectDir, '.gitlab-ci.yml');
    $this->localMachineHelper->getFilesystem()->dumpFile($gitlabCiFilepath, Yaml::dump($contents, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    $this->localMachineHelper->getFilesystem()->remove($acquiaPipelinesFileName);
  }

  /**
   * @param array $keywords
   */
  private function assignStageFromKeywords(array $keywords, string $haystack): ?string {
    foreach ($keywords as $needle => $stage) {
      if (str_contains($haystack, $needle)) {
        return $stage;
      }
    }
    return NULL;
  }

}
