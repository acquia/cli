<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package Acquia\Cli\Tests\Application
 */
class ApplicationTestBase extends TestBase {

  /**
   * Symfony kernel.
   *
   * @var \Acquia\Cli\Kernel
   */
  protected Kernel $kernel;

  public function setUp($output = NULL):void {
    parent::setUp($output);
    $this->kernel = new Kernel('dev', 0);
    $this->kernel->boot();
    $this->kernel->getContainer()->set(CloudDataStore::class, $this->datastoreCloud);
    $this->kernel->getContainer()->set(ClientService::class, $this->clientServiceProphecy->reveal());
    $output = new BufferedOutput();
    $this->kernel->getContainer()->set(OutputInterface::class, $output);
  }

  /**
   * @return string
   * @throws \Exception
   */
  protected function runApp(): string {
    putenv("ACLI_REPO_ROOT=" . $this->projectFixtureDir);
    $input = $this->kernel->getContainer()->get(InputInterface::class);
    $output = $this->kernel->getContainer()->get(OutputInterface::class);
    /** @var Application $application */
    $application = $this->kernel->getContainer()->get(Application::class);
    $application->setAutoExit(FALSE);
    // Set column width to prevent wrapping and string assertion failures.
    putenv('COLUMNS=85');
    $application->run($input, $output);
    return $output->fetch();
  }

  protected function setInput($args): void {
    $input = new ArrayInput($args);
    $input->setInteractive(FALSE);
    $this->kernel->getContainer()->set(InputInterface::class, $input);
  }

}
