<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\Api\ApiCommandFactory;
use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ApplicationTestBase extends TestBase
{
    /**
     * Symfony kernel.
     */
    protected Kernel $kernel;

    public function setUp(): void
    {
        parent::setUp();
        $this->kernel = new Kernel('dev', false);
        $this->kernel->boot();
        $this->kernel->getContainer()
            ->set(CloudDataStore::class, $this->datastoreCloud);
        $this->kernel->getContainer()
            ->set(ClientService::class, $this->clientServiceProphecy->reveal());
        $output = new BufferedOutput();
        $this->kernel->getContainer()->set(OutputInterface::class, $output);
    }

    protected function runApp(): string
    {
        putenv("ACLI_REPO_ROOT=" . $this->projectDir);
        putenv("ACLI_VERSION=" . 'dev-unknown');
        $input = $this->kernel->getContainer()->get(InputInterface::class);
        $output = $this->kernel->getContainer()->get(OutputInterface::class);
        /** @var Application $application */
        $application = $this->kernel->getContainer()->get(Application::class);
        $container = $this->kernel->getContainer();
        $helper = $container->get(ApiCommandHelper::class);
        $application->addCommands($helper->getApiCommands(__DIR__ . '/../assets/acquia-spec.json', 'api', $container->get(ApiCommandFactory::class)));
        $application->setAutoExit(false);
        $application->run($input, $output);
        return $output->fetch();
    }

    protected function setInput(array $args): void
    {
        // ArrayInput requires command to be the first parameter.
        if (array_key_exists('command', $args)) {
            $newArgs = [];
            $newArgs['command'] = $args['command'];
            unset($args['command']);
            $args = array_merge($newArgs, $args);
        }
        $input = new ArrayInput($args);
        $input->setInteractive(false);
        $this->kernel->getContainer()->set(InputInterface::class, $input);
    }
}
