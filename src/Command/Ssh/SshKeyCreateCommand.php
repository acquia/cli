<?php

namespace Acquia\Ads\Command\Ssh;

use Symfony\Component\Console\Input\InputInterface;
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
class SshKeyCreateCommand extends SshKeyCommandBase
{

    /**
     * The default command name.
     *
     * @var string
     */
    protected static $defaultName = 'ssh-key:create';

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('Create an ssh key on your local machine');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createSshKey();

        return 0;
    }

    /**
     * @return mixed
     */
    protected function promptForFilename()
    {
        $question = new Question('<question>Please enter a filename for your new local SSH key:</question> ');
        $question->setNormalizer(static function ($value) {
            return $value ? trim($value) : '';
        });
        $question->setValidator(static function ($answer) {
            $violations = Validation::createValidator()->validate($answer, [
              new Length(['min' => 5]),
              new NotBlank(),
              new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces'])
            ]);
            if (count($violations)) {
                throw new ValidatorException($violations->get(0)->getMessage());
            }

            return $answer;
        });

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    /**
     * @return mixed
     */
    protected function promptForPassword()
    {
        $question = new Question('<question>Enter a password for your SSH key:</question> ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $question->setValidator(function ($answer) {
            $violations = Validation::createValidator()->validate($answer, [
              new NotBlank(),
            ]);
            if (count($violations)) {
                throw new ValidatorException($violations->get(0)->getMessage());
            }

            return $answer;
        });

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    /**
     * @return string
     */
    protected function createSshKey(): string
    {
        $filename = $this->promptForFilename();
        $password = $this->promptForPassword();

        $filepath = $this->getApplication()->getLocalMachineHelper()->getHomeDir() . '/.ssh/' . $filename;
        $this->getApplication()->getLocalMachineHelper()->execute([
          'ssh-keygen',
          '-b',
          '4096',
          '-f',
          $filepath,
          '-N',
          $password,
        ]);

        return $filepath;
    }
}
