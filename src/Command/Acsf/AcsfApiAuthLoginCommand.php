<?php

namespace Acquia\Cli\Command\Acsf;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
    if ($this->acsfClientService->isMachineAuthenticated($this->datastoreCloud)) {
      $answer = $this->io->confirm('Your machine has already been authenticated with Site Factory API, would you like to re-authenticate?');
      if (!$answer) {
        return 0;
      }
    }

    // If keys already are saved locally, prompt to select.
    if ($input->isInteractive() && $keys = $this->datastoreCloud->get('acsf_keys')) {
      $keys['create_new'] = [
        'username' => 'Enter a new API key',
      ];
      $selected_key = $this->promptChooseFromObjectsOrArrays($keys, 'username', 'username', 'Choose which API key to use');
      if ($selected_key['uuid'] !== 'create_new') {
        $this->datastoreCloud->set('acsf_key', $selected_key['uuid']);
        $output->writeln("<info>Acquia CLI will use the API Key <options=bold>{$selected_key['label']}</></info> for URL");
        return 0;
      }
    }

    $this->askForOptionValue($input, 'factory-url');
    $this->askForOptionValue($input, 'username');
    $this->askForOptionValue($input, 'password', TRUE);

    $factory_url = $input->getOption('factory-url');
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
    $keys = $this->datastoreCloud->get('acsf_keys');
    $keys[$factory_url]['users'][$username] = [
      'username' => $username,
      'password' => $password,
    ];
    $keys[$factory_url]['active_user'] = $username;
    $this->datastoreCloud->set('acsf_keys', $keys);
    $this->datastoreCloud->set('acsf_factory', $factory_url);
  }

}
