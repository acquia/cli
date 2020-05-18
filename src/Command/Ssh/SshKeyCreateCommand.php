<?php

namespace Acquia\Cli\Command\Ssh;

use Acquia\Cli\Exception\AcquiaCliException;
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

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ssh-key:create')
      ->setDescription('Create an ssh key on your local machine')
      ->addOption('filename', NULL, InputOption::VALUE_REQUIRED, 'The filename of the SSH key')
      ->addOption('password', NULL, InputOption::VALUE_REQUIRED, 'The password for the SSH key');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->createSshKey($input, $output);

    return 0;
  }

  /**
   * @return string
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function createSshKey($input, $output): string {
    $filename = $this->determineFilename($input, $output);
    $password = $this->determinePassword($input, $output);

    $filepath = $this->getApplication()->getSshKeysDir() . '/' . $filename;
    if (file_exists($filepath)) {
      throw new AcquiaCliException('An SSH key with the filename {filepath} already exists. Please delete it and retry.', ['filepath' => $filepath]);
    }

    $process = $this->getApplication()->getLocalMachineHelper()->execute([
      'ssh-keygen',
      '-b',
      '4096',
      '-f',
      $filepath,
      '-N',
      $password,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException($process->getOutput());
    }

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
      $question = new Question('<question>Please enter a filename for your new local SSH key:</question> ');
      $question->setNormalizer(static function ($value) {
        return $value ? trim($value) : '';
      });
      $question->setValidator(\Closure::fromCallable([$this, 'validateFilename']));
      $filename = $this->questionHelper->ask($input, $output, $question);
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
   */
  protected function determinePassword(InputInterface $input, OutputInterface $output): string {
    if ($input->getOption('password')) {
      $password = $input->getOption('password');
      $this->validateFilename($password);
    }
    else {
      $question = new Question('<question>Enter a password for your SSH key:</question> ');
      $question->setHidden($this->getApplication()->getLocalMachineHelper()->useTty());
      $question->setNormalizer(static function ($value) {
        return $value ? trim($value) : '';
      });
      $question->setValidator(\Closure::fromCallable([$this, 'validatePassword']));
      $password = $this->questionHelper->ask($input, $output, $question);
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
      new NotBlank(),
    ]);
    if (count($violations)) {
      throw new ValidatorException($violations->get(0)->getMessage());
    }

    return $password;
  }

}
