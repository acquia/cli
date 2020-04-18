<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Command\CommandBase;
use AlecRabbit\Snake\Spinner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class SshKeyUploadCommand.
 */
class SshKeyUploadCommand extends SshKeyCommandBase
{

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ssh-key:upload')
          ->setDescription('Upload a local SSH key to Acquia Cloud');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $local_keys = $this->findLocalSshKeys();
        $labels = [];
        foreach ($local_keys as $local_key) {
            $labels[] = $local_key->getFilename();
        }
        $question = new ChoiceQuestion('<question>Choose a local SSH key to upload to Acquia Cloud</question>:', $labels);
        $helper = $this->getHelper('question');
        $answer = $helper->ask($this->input, $this->output, $question);

        foreach ($local_keys as $local_key) {
            if ($local_key->getFilename() === $answer) {
                $filepath = $local_key->getPath();
                break;
            }
        }

        $options = [
          'form_params' => [
            'label' => $answer,
            'public_key' => file_get_contents($filepath),
          ],
        ];
        // $response = $acquia_cloud_client->makeRequest('post', '/account/ssh-keys/', $options);

        $this->output->writeln("<info>Uploaded $answer to Acquia Cloud.</info>");
        $this->output->writeln('<info>Waiting for new key to be provisioned on Acquia Cloud servers</info>');

        $spinner = new Spinner();
        $spinner->begin();
        $time_start = time();
        while (true) {
            $time_end = time();
            $time_elapsed = $time_end - $time_start;
            if ($time_elapsed > 5) {
                $time_start = time();
                $this->output->writeln('Polling!');
            } else {
                for ($i = 0; $i <= 50; $i++) {
                    usleep(80000);
                    $spinner->spin();
                }
            }
        }
        $spinner->end();

        return 0;
    }
}
