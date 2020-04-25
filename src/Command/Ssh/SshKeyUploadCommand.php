<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Exception\AdsException;
use AcquiaCloudApi\Connector\Client;
use AlecRabbit\Snake\Spinner;
use React\EventLoop\Factory;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

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
          ->setDescription('Upload a local SSH key to Acquia Cloud')
          ->addOption('filepath', null, InputOption::VALUE_REQUIRED, 'The filepath of the public SSH key to upload');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     * @throws \Acquia\Ads\Exception\AdsException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $acquia_cloud_client = $this->getAcquiaCloudClient();
        $local_keys = $this->findLocalSshKeys();

        if ($input->hasOption('filepath')) {
            $public_key = file_get_contents($input->getOption('filepath'));
            $chosen_local_key = basename($input->getOption('filepath'));
        } else {
            // Get local key and contents.
            $chosen_local_key = $this->promptChooseLocalSshKey($local_keys);
            $public_key = $this->getLocalSshKeyContents($local_keys, $chosen_local_key);
        }

        // Get label.
        $label = $this->promptSshKeyLabel();

        $options = [
          'form_params' => [
            'label' => $label,
            'public_key' => $public_key,
          ],
        ];
        $response = $acquia_cloud_client->makeRequest('post', '/account/ssh-keys', $options);
        if ($response->getStatusCode() != 202) {
            throw new AdsException($response->getBody()->getContents());
        }

        // Wait for the key to register on Acquia Cloud.
        $this->output->writeln("<info>Uploaded $chosen_local_key to Acquia Cloud with label $label</info>");
        $this->output->write('Waiting for new key to be provisioned on Acquia Cloud servers...');
        $this->pollAcquiaCloud($output, $acquia_cloud_client, $public_key);

        return 0;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \AcquiaCloudApi\Connector\Client $acquia_cloud_client
     * @param string $public_key
     */
    protected function pollAcquiaCloud(
        OutputInterface $output,
        Client $acquia_cloud_client,
        string $public_key
    ): void {
        // Create a loop to periodically poll Acquia Cloud.
        $loop = Factory::create();

        // Add a spinner.
        $spinner = new Spinner();
        $loop->addPeriodicTimer($spinner->interval(), static function () use ($spinner) {
            $spinner->spin();
        });

        // Poll Cloud every 5 seconds.
        $loop->addPeriodicTimer(5, static function () use ($output, $loop, $acquia_cloud_client, $public_key) {
            // @todo Change this to test an actual ssh connection, not just Cloud API.
            // But which server do we check a connection to?
            $response = $acquia_cloud_client->makeRequest('get', '/account/ssh-keys');
            $cloud_keys = $acquia_cloud_client->processResponse($response);
            foreach ($cloud_keys as $cloud_key) {
                if (trim($cloud_key->public_key) === trim($public_key)) {
                    $output->writeln("\n<info>Your SSH key is ready for use.</info>");
                    $loop->stop();
                }
            }
        });

        // Add a 10 minute timeout.
        $loop->addTimer(10 * 60, function () use ($loop) {
            $this->logger->debug('Timed out after 10 minutes!');
            $loop->stop();
        });

        // Start the loop and spinner.
        $spinner->begin();
        $loop->run();
        $spinner->end();
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo[] $local_keys
     *
     * @return string
     */
    protected function promptChooseLocalSshKey($local_keys): string
    {
        $labels = [];
        foreach ($local_keys as $local_key) {
            $labels[] = $local_key->getFilename();
        }
        $question = new ChoiceQuestion(
            '<question>Choose a local SSH key to upload to Acquia Cloud</question>:',
            $labels
        );
        $helper = $this->getHelper('question');
        $answer = $helper->ask($this->input, $this->output, $question);

        return $answer;
    }

    /**
     * @return string
     */
    protected function promptSshKeyLabel(): string
    {
        $question = new Question('<question>Please enter a Acquia Cloud label for this SSH key:</question> ');
        $question->setNormalizer(static function ($value) {
            // It may only contain letters, numbers and underscores,
            $value = preg_replace('/[^A-Za-z0-9_]/', '', $value);

            return $value;
        });
        $question->setValidator(function ($answer) {
            if (trim($answer) === '') {
                throw new RuntimeException('The label cannot be empty');
            }

            return $answer;
        });
        $label = $this->questionHelper->ask($this->input, $this->output, $question);

        return $label;
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo[] $local_keys
     * @param string $chosen_local_key
     *
     * @return false|string
     */
    protected function getLocalSshKeyContents($local_keys, string $chosen_local_key)
    {
        foreach ($local_keys as $local_key) {
            if ($local_key->getFilename() === $chosen_local_key) {
                $filepath = $local_key->getRealPath();
                break;
            }
        }
        $public_key = file_get_contents($filepath);

        return $public_key;
    }
}
