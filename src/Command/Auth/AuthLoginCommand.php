<?php

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    /** @var \Webmozart\KeyValueStore\JsonFileStore $cloud_datastore */
    if (CommandBase::isMachineAuthenticated($this->datastoreCloud)) {
      $question = new ConfirmationQuestion('<question>Your machine has already been authenticated with Acquia Cloud API, would you like to re-authenticate?</question> ',
        TRUE);
      $answer = $this->questionHelper->ask($this->input, $this->output, $question);
      if (!$answer) {
        return 0;
      }
    }
    $this->promptOpenBrowserToCreateToken($input, $output);

    $api_key = $this->determineApiKey($input, $output);
    $api_secret = $this->determineApiSecret($input, $output);
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
      $question = new Question('<question>Please enter your API Key:</question>' );
      $question->setValidator(\Closure::fromCallable([$this, 'validateApiKey']));
      $api_key = $this->questionHelper->ask($input, $output, $question);
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
      $api_secret = $this->questionHelper->ask($input, $output, $question);
    }

    return $api_secret;
  }

  /**
   * @param string $api_key
   * @param string $api_secret
   *
   * @throws \Exception
   */
  protected function writeApiCredentialsToDisk($api_key, $api_secret): void {
    $this->datastoreCloud->set('key', $api_key);
    $this->datastoreCloud->set('secret', $api_secret);
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
      $this->output->writeln("You will need an Acquia Cloud API token from <href=$token_url>$token_url</>");
      $this->output->writeln('You should create a new token specifically for Developer Studio and enter the associated key and secret below.');

      if (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
        $question = new ConfirmationQuestion('<question>Do you want to open this page to generate a token now?</question> ',
          TRUE);
        if ($this->questionHelper->ask($input, $output, $question)) {
          $this->localMachineHelper->startBrowser($token_url);
        }
      }
    }
  }

}
