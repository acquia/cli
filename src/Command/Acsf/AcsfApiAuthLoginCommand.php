<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class AcsfLoginCommand.
 */
class AcsfApiAuthLoginCommand extends AcsfCommandBase {

  protected static $defaultName = 'acsf:auth:login';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Register your Site Factory API key and secret to use API functionality')
      ->addOption('username', 'u', InputOption::VALUE_REQUIRED, "The username for the Site Factory that you'd like to login to")
      ->addOption('password', 'p', InputOption::VALUE_REQUIRED, "The password for your Site Factory user")
      ->addOption('factory-url', 'f', InputOption::VALUE_REQUIRED, "The URL of your factory. E.g., https://www.acquia.com");
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
    if ($input->getOption('factory-url')) {
      $factory_url = $input->getOption('factory-url');
    }
    elseif ($input->isInteractive() && $this->datastoreCloud->get('acsf_factories')) {
      $factories = $this->datastoreCloud->get('acsf_factories');
      $factory_choices = $factories;
      $factory_choices['add_new'] = [
        'url' => 'Enter a new factory URL',
      ];
      $factory = $this->promptChooseFromObjectsOrArrays($factory_choices, 'url', 'url', 'Please choose a Factory to login to');
      if ($factory['url'] === 'Enter a new factory URL') {
        $factory_url = $this->io->ask('Enter the full URL of the factory');
        $factory = [
          'url' => $factory_url,
          'users' => [],
        ];
      }
      else {
        $factory_url = $factory['url'];
      }

      $users = $factory['users'];
      $users['add_new'] = [
        'username' => 'Enter a new user',
      ];
      $selected_user = $this->promptChooseFromObjectsOrArrays($users, 'username', 'username', 'Choose which user to login as');
      if ($selected_user['username'] !== 'Enter a new user') {
        $this->datastoreCloud->set('acsf_active_factory', $factory_url);
        $factories[$factory_url]['active_user'] = $selected_user['username'];
        $this->datastoreCloud->set('acsf_factories', $factories);
        $output->writeln([
          "<info>Acquia CLI is now logged in to <options=bold>{$factory['url']}</> as <options=bold>{$selected_user['username']}</></info>",
        ]);
        return 0;
      }
    }
    else {
      $factory_url = $this->askForOptionValue($input, 'factory-url');
    }

    $this->askForOptionValue($input, 'username');
    $this->askForOptionValue($input, 'password', TRUE);

    $username = $input->getOption('username');
    $password = $input->getOption('password');
    $this->writeAcsfCredentialsToDisk($factory_url, $username, $password);
    $output->writeln("<info>Saved credentials to <options=bold>{$this->cloudConfigFilepath}</></info>");

    return 0;
  }

  /**
   * @param string $factory_url
   * @param string $username
   * @param string $password
   *
   */
  protected function writeAcsfCredentialsToDisk($factory_url, string $username, string $password): void {
    $keys = $this->datastoreCloud->get('acsf_factories');
    $keys[$factory_url]['users'][$username] = [
      'username' => $username,
      'password' => $password,
    ];
    $keys[$factory_url]['url'] = $factory_url;
    $keys[$factory_url]['active_user'] = $username;
    $this->datastoreCloud->set('acsf_factories', $keys);
    $this->datastoreCloud->set('acsf_active_factory', $factory_url);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param string $option_name
   * @param bool $hidden
   *
   * @return mixed|null
   */
  protected function askForOptionValue(InputInterface $input, string $option_name, bool $hidden = FALSE) {
    if (!$input->getOption($option_name)) {
      $option = $this->getDefinition()->getOption($option_name);
      $this->io->note([
        "Please a value for $option_name",
        $option->getDescription(),
      ]);
      $question = new Question("Please enter a value for $option_name", $option->getDefault());
      $question->setMaxAttempts(NULL);
      $question->setHidden($hidden);
      $answer = $this->io->askQuestion($question);
      $input->setOption($option_name, $answer);
      return $answer;
    }
    return NULL;
  }

}
