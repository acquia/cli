<?php

namespace Acquia\Ads\Command;

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
 * Class CreateProjectCommand
 *
 * @package Grasmash\YamlCli\Command
 */
class AuthCommand extends CommandBase
{
    private $cloudApiConfFilePath;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('auth:login')
          ->setDescription('Register your Cloud API key and secret to use API functionality')
          ->addOption('key', 'k', InputOption::VALUE_REQUIRED)
          ->addOption('secret', 's', InputOption::VALUE_REQUIRED);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // @todo Check if user is already authenticated.
        $this->promptOpenBrowserToCreateToken($input, $output);

        $api_key = $this->determineApiKey($input, $output);
        $api_secret = $this->determineApiSecret($input, $output);
        $this->writeApiCredentialsToDisk($api_key, $api_secret);

        $output->writeln("<info>Saved credentials to {$this->getCloudApiConfFilePath()}</info>");

        return 0;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return string
     */
    protected function determineApiKey(InputInterface $input, OutputInterface $output): string
    {
        if ($input->getOption('key')) {
            $api_key = $input->getOption('key');
            $this->validateApiKey($api_key);
        } else {
            $question = new Question('<question>Please enter your API Key:</question>');
            $question->setValidator(\Closure::fromCallable([$this, 'validateApiKey']));
            $api_key = $this->questionHelper->ask($input, $output, $question);
        }

        return $api_key;
    }

    /**
     * @param $key
     *
     * @return string
     */
    protected function validateApiKey($key): string
    {
        $violations = Validation::createValidator()->validate($key, [
          new Length(['min' => 10]),
          new NotBlank(),
          new Regex(['pattern' => '/^\S*$/', 'message' => 'The value may not contain spaces'])
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }
        return $key;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return string
     */
    protected function determineApiSecret(InputInterface $input, OutputInterface $output): string
    {
        if ($input->getOption('secret')) {
            $api_secret = $input->getOption('secret');
            $this->validateApiKey($api_secret);
        } else {
            $question = new Question('<question>Please enter your API Secret:</question>');
            $question->setHidden($this->getApplication()->getLocalMachineHelper()->useTty());
            $question->setHiddenFallback(true);
            $question->setValidator(\Closure::fromCallable([$this, 'validateApiKey']));
            $api_secret = $this->questionHelper->ask($input, $output, $question);
        }

        return $api_secret;
    }

    /**
     * @param array $api_key
     * @param $api_secret
     */
    protected function writeApiCredentialsToDisk($api_key, $api_secret): void
    {
        $file_contents = [
          'key' => $api_key,
          'secret' => $api_secret,
        ];
        $filepath = $this->getCloudApiConfFilePath();
        $this->getApplication()
          ->getLocalMachineHelper()
          ->getFilesystem()
          ->dumpFile($filepath, json_encode($file_contents, JSON_PRETTY_PRINT));
    }

    /**
     * @param $filepath
     */
    public function setCloudApiConfFilePath($filepath): void
    {
        $this->cloudApiConfFilePath = $filepath;
    }

    /**
     * @return mixed
     */
    public function getCloudApiConfFilePath()
    {
        if (isset($this->cloudApiConfFilePath)) {
            return $this->cloudApiConfFilePath;
        }

        return $this->getApplication()->getLocalMachineHelper()->getHomeDir() . '/.acquia/cloud_api.conf';
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function promptOpenBrowserToCreateToken(
        InputInterface $input,
        OutputInterface $output
    ): void {
        if (!$input->getOption('key') || !$input->getOption('secret')) {
            $token_url = 'https://cloud.acquia.com/a/profile/tokens';
            $this->output->writeln("You will need an Acquia Cloud API token from <href=$token_url>$token_url</>.");
            $this->output->writeln('You should create a new token specifically for Developer Studio and enter the associated key and secret below.');

            $question = new ConfirmationQuestion(
                '<question>Do you want to open this page to generate a token now?</question>',
                true
            );
            if ($this->questionHelper->ask($input, $output, $question)) {
                $this->getApplication()->getLocalMachineHelper()->startBrowser($token_url);
            }
        }
    }
}
