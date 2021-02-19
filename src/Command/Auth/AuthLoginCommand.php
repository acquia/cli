<?php

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Connector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

/**
 * Class AuthLoginCommand.
 */
class AuthLoginCommand extends CommandBase {

  protected static $defaultName = 'auth:login';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Register your Cloud API key and secret to use API functionality')
      ->setAliases(['login'])
      ->addOption('key', 'k', InputOption::VALUE_REQUIRED)
      ->addOption('secret', 's', InputOption::VALUE_REQUIRED);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *
   * @return bool
   */
  protected function commandRequiresAuthentication(InputInterface $input): bool {
    return FALSE;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    /** @var \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore */
    if (CommandBase::isMachineAuthenticated($this->datastoreCloud)) {
      $answer = $this->io->confirm('Your machine has already been authenticated with the Cloud Platform API, would you like to re-authenticate?');
      if (!$answer) {
        return 0;
      }
    }

    // If keys already are saved locally, prompt to select.
    if ($keys = $this->datastoreCloud->get('keys')) {
      $keys['create_new'] = [
        'uuid' => 'create_new',
        'label' => 'Create a new API key',
      ];
      $selected_key = $this->promptChooseFromObjectsOrArrays($keys, 'uuid', 'label', 'Choose which API key to use');
      if ($selected_key['uuid'] !== 'create_new') {
        $this->datastoreCloud->set('acli_key', $selected_key['uuid']);
        $output->writeln("<info>Acquia CLI will use the API Key <options=bold>{$selected_key['label']}</></info>");
        $secret = $this->datastoreCloud->get('keys')[$selected_key['uuid']]['secret'];
        $this->reAuthenticate($selected_key['uuid'], $secret);
        return 0;
      }
    }

    $this->promptOpenBrowserToCreateToken($input, $output);
    $api_key = $this->determineApiKey($input, $output);
    $api_secret = $this->determineApiSecret($input, $output);
    $this->reAuthenticate($api_key, $api_secret);
    $this->writeApiCredentialsToDisk($api_key, $api_secret);
    $output->writeln("<info>Saved credentials to <options=bold>{$this->cloudConfigFilepath}</></info>");

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   */
  protected function determineApiKey(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('key')) {
      $api_key = $input->getOption('key');
      $this->validateApiKey($api_key);
    }
    else {
      $api_key = $this->io->ask('Please enter your API Key', NULL, \Closure::fromCallable([$this, 'validateApiKey']));
    }

    return $api_key;
  }

  /**
   * @param $key
   *
   * @return string
   */
  protected function validateApiKey($key): string {
    $violations = Validation::createValidator()->validate($key, [
      new Length(['min' => 10]),
      new NotBlank(),
      new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }
    return $key;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   * @throws \Exception
   */
  protected function determineApiSecret(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('secret')) {
      $api_secret = $input->getOption('secret');
      $this->validateApiKey($api_secret);
    }
    else {
      $question = new Question('<question>Please enter your API Secret:</question> ');
      $question->setHidden($this->localMachineHelper->useTty());
      $question->setHiddenFallback(TRUE);
      $question->setValidator(\Closure::fromCallable([$this, 'validateApiKey']));
      $api_secret = $this->io->askQuestion($question);
    }

    return $api_secret;
  }

  /**
   * @param string $api_key
   * @param string $api_secret
   *
   * @throws \Exception
   */
  protected function writeApiCredentialsToDisk(string $api_key, string $api_secret): void {
    $token_info = $this->cloudApiClientService->getClient()->request('get', "/account/tokens/{$api_key}");
    $keys = $this->datastoreCloud->get('keys');
    $keys[$api_key] = [
      'label' => $token_info->label,
      'uuid' => $api_key,
      'secret' => $api_secret,
    ];
    $this->datastoreCloud->set('keys', $keys);
    $this->datastoreCloud->set('acli_key', $api_key);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \Exception
   */
  protected function promptOpenBrowserToCreateToken(
        InputInterface $input,
        OutputInterface $output
    ): void {
    if (!$input->getOption('key') || !$input->getOption('secret')) {
      $token_url = 'https://cloud.acquia.com/a/profile/tokens';
      $this->output->writeln("You will need a Cloud Platform API token from <href=$token_url>$token_url</>");

      if (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
        if ($this->io->confirm('Do you want to open this page to generate a token now?')) {
          $this->localMachineHelper->startBrowser($token_url);
        }
      }
    }
  }

  /**
   * @param string $api_key
   * @param string $api_secret
   */
  protected function reAuthenticate(string $api_key, string $api_secret): void {
    // Client service needs to be reinitialized with new credentials in case
    // this is being run as a sub-command.
    // @see https://github.com/acquia/cli/issues/403
    $this->cloudApiClientService->setConnector(new Connector([
      'key' => $api_key,
      'secret' => $api_secret
    ]));
  }

}
