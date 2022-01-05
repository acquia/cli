<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Exception\AcquiaCliException;
use Closure;
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
 * Class SshKeyCreateCommand.
 */
class SshKeyCreateCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:create';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Create an SSH key on your local machine')
      ->addOption('filename', NULL, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $filename = $this->determineFilename($input, $output);
    $password = $this->determinePassword($input, $output);
    $this->createSshKey($filename, $password);
    $output->writeln('<info>Created new SSH key.</info> ' . $this->publicSshKeyFilepath);

    return 0;
  }

}
