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
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key')
      ->addOption('is-wizard', FALSE, InputOption::VALUE_REQUIRED, 'The flag for IDE wizard');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $key_file_path = $this->createSshKey($input, $output);
    $output->writeln('<info>Created new SSH key.</info> ' . $key_file_path);

    return 0;
  }

  /**
   * @param $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function createSshKey($input, OutputInterface $output): string {
    $filename = $this->determineFilename($input, $output);
    $password = $this->determinePassword($input, $output);

    $filepath = $this->sshDir . '/' . $filename;
    if (file_exists($filepath)) {
      throw new AcquiaCliException('An SSH key with the filename {filepath} already exists. Please delete it and retry', ['filepath' => $filepath]);
    }

    $process = $this->localMachineHelper->execute([
      'ssh-keygen',
      '-b',
      '4096',
      '-f',
      $filepath,
      '-N',
      $password,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException($process->getOutput() . $process->getErrorOutput());
    }
    // @todo Validate or set file permissions.

    return $filepath;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   */
  protected function determineFilename(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('filename')) {
      $filename = $input->getOption('filename');
      $this->validateFilename($filename);
    }
    else {
      $default = 'id_rsa';
      $question = new Question("Please enter a filename for your new local SSH key. Press enter to use default value", $default);
      $question->setNormalizer(static function ($value) {
        return $value ? trim($value) : '';
      });
      $question->setValidator(Closure::fromCallable([$this, 'validateFilename']));
      $filename = $this->io->askQuestion($question);
    }

    return $filename;
  }

  /**
   * @param $filename
   *
   * @return mixed
   */
  protected function validateFilename($filename) {
    $violations = Validation::createValidator()->validate($filename, [
      new Length(['min' => 5]),
      new NotBlank(),
      new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces']),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $filename;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return string
   * @throws \Exception
   */
  protected function determinePassword(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('is-wizard')) {
      $password = "";
    }
    else {
      if ($input->getOption('password')) {
        $password = $input->getOption('password');
        $this->validatePassword($password);
      } else {
        $question = new Question('Enter a password for your SSH key');
        $question->setHidden($this->localMachineHelper->useTty());
        $question->setNormalizer(static function ($value) {
          return $value ? trim($value) : '';
        });
        $question->setValidator(Closure::fromCallable([$this, 'validatePassword']));
        $password = $this->io->askQuestion($question);
      }
    }

    return $password;
  }

  /**
   * @param $password
   *
   * @return mixed
   */
  protected function validatePassword($password) {
    $violations = Validation::createValidator()->validate($password, [
      new Length(['min' => 5]),
      new NotBlank(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $password;
  }

}
