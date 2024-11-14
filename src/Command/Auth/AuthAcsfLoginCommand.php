<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Auth;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:acsf-login', description: 'Register Site Factory API credentials')]
final class AuthAcsfLoginCommand extends CommandBase
{
    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, "Your Site Factory username")
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED, "Your Site Factory key")
            ->addOption('factory-url', 'f', InputOption::VALUE_REQUIRED, "Your Site Factory URL (including https://)");
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('factory-url')) {
            $factoryUrl = $input->getOption('factory-url');
        } elseif ($input->isInteractive() && $this->datastoreCloud->get('acsf_factories')) {
            $factories = $this->datastoreCloud->get('acsf_factories');
            $factoryChoices = $factories;
            foreach ($factoryChoices as $url => $factoryChoice) {
                $factoryChoices[$url]['url'] = $url;
            }
            $factoryChoices['add_new'] = [
                'url' => 'Enter a new factory URL',
            ];
            $factory = $this->promptChooseFromObjectsOrArrays($factoryChoices, 'url', 'url', 'Choose a Factory to login to');
            if ($factory['url'] === 'Enter a new factory URL') {
                $factoryUrl = $this->determineOption('factory-url', false, $this->validateUrl(...));
                $factory = [
                    'url' => $factoryUrl,
                    'users' => [],
                ];
            } else {
                $factoryUrl = $factory['url'];
            }

            $users = $factory['users'];
            $users['add_new'] = [
                'username' => 'Enter a new user',
            ];
            $selectedUser = $this->promptChooseFromObjectsOrArrays($users, 'username', 'username', 'Choose which user to login as');
            if ($selectedUser['username'] !== 'Enter a new user') {
                $this->datastoreCloud->set('acsf_active_factory', $factoryUrl);
                $factories[$factoryUrl]['active_user'] = $selectedUser['username'];
                $this->datastoreCloud->set('acsf_factories', $factories);
                $output->writeln([
                    "<info>Acquia CLI is now logged in to <options=bold>{$factory['url']}</> as <options=bold>{$selectedUser['username']}</></info>",
                ]);
                return Command::SUCCESS;
            }
        } else {
            $factoryUrl = $this->determineOption('factory-url', false, $this->validateUrl(...));
        }

        $username = $this->determineOption('username');
        $key = $this->determineOption('key', true, $this->validateApiKey(...));

        $this->writeAcsfCredentialsToDisk($factoryUrl, $username, $key);
        $output->writeln("<info>Saved credentials</info>");

        return Command::SUCCESS;
    }

    private function writeAcsfCredentialsToDisk(?string $factoryUrl, string $username, string $key): void
    {
        $keys = $this->datastoreCloud->get('acsf_factories');
        $keys[$factoryUrl]['users'][$username] = [
            'key' => $key,
            'username' => $username,
        ];
        $keys[$factoryUrl]['url'] = $factoryUrl;
        $keys[$factoryUrl]['active_user'] = $username;
        $this->datastoreCloud->set('acsf_factories', $keys);
        $this->datastoreCloud->set('acsf_active_factory', $factoryUrl);
    }
}
