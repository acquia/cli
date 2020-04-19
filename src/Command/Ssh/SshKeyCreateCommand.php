<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Command\CommandBase;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class SshKeyCreateCommand.
 */
class SshKeyCreateCommand extends SshKeyCommandBase
{
    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ssh-key:create')
          ->setDescription('Create an ssh key on your local machine');
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
        $question->setValidator(function ($answer) {
            if (!is_string($answer) || preg_match("/\s/", $answer)) {
                throw new RuntimeException('The filename cannot contain any spaces');
            }
            if (trim($answer) === '') {
                throw new RuntimeException('The filename cannot be empty');
            }

            return $answer;
        });
        $filename = $this->questionHelper->ask($this->input, $this->output, $question);

        return $filename;
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
            if (trim($answer) === '') {
                throw new RuntimeException('The password cannot be empty');
            }

            return $answer;
        });
        $password = $this->questionHelper->ask($this->input, $this->output, $question);

        return $password;
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
          $password
        ]);

        return $filepath;
    }
}
