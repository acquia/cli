<?php

namespace Acquia\Ads\Command;

use Acquia\Ads\Exec\ExecTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use GuzzleHttp\Client;

/**
 * Class CreateProjectCommand
 *
 * @package Grasmash\YamlCli\Command
 */
class AuthCommand extends CommandBase
{

    use ExecTrait;

    /**
     * {inheritdoc}
     */
    protected function configure()
    {
        $this->setName('auth:login')
          ->setDescription('register your Cloud API key and secret to use API functionality');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $token_url = "https://cloud.acquia.com/a/profile/tokens";
        $this->output->writeln("You will need an Acquia Cloud API token from <href=$token_url>$token_url</>.");
        $this->output->writeln("You should create a new token specifically for Developer Studio and enter the associated key and secret below.");

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('<question>Do you want to open this page to generate a token now?</question>', true);
        if ($helper->ask($input, $output, $question)) {
            $this->startBrowser($token_url);
        }

        // Open browser.
        $question = new Question('<question>Please enter your API Key:</question>');
        $api_key = $helper->ask($input, $output, $question);

        $question = new Question('<question>Please enter your API Secret:</question>');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $api_secret = $helper->ask($input, $output, $question);

        $file_contents = [
            'key' => $api_key,
            'secret' => $api_secret,
        ];
        $this->fs->dumpFile($this->getHomeDir() . '/.acquia/cloud_api.conf', json_encode($file_contents, JSON_PRETTY_PRINT));
    }
}
