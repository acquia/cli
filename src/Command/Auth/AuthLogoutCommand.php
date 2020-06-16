<?php

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Closure;
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
 * Class AuthLogoutCommand.
 */
class AuthLogoutCommand extends CommandBase {

  protected static $defaultName = 'auth:logout';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Remove Cloud API key and secret from local machine.')
      ->setAliases(['logout']);
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
      $question = new ConfirmationQuestion('<question>Are you sure you\'d like to remove your Acquia Cloud API login credentials from this machine?</question> ',
        TRUE);
      $answer = $this->questionHelper->ask($this->input, $this->output, $question);
      if (!$answer) {
        return 0;
      }
    }
    $this->datastoreCloud->set('key', NULL);
    $this->datastoreCloud->set('secret', NULL);

    $output->writeln("Removed Cloud API credentials from <options=bold>{$this->cloudConfigFilepath}</></info>");

    return 0;
  }

}
