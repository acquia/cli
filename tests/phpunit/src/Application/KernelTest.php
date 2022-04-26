<?php

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Application;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Kernel;
use Acquia\Cli\Tests\TestBase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package Acquia\Cli\Tests\Application
 */
class KernelTest extends TestBase {

  /**
   * Symfony kernel.
   *
   * @var \Acquia\Cli\Kernel
   */
  protected Kernel $kernel;

  public function setUp($output = NULL):void {
    parent::setUp($output);
    // If kernel is cached from a previous run, it doesn't get detected in code
    // coverage reports.
    $this->fs->remove('var/cache');
    $this->kernel = new Kernel('dev', 0);
    $this->kernel->boot();
    $this->kernel->getContainer()->set(CloudDataStore::class, $this->datastoreCloud);
    $this->kernel->getContainer()->set(ClientService::class, $this->clientServiceProphecy->reveal());
    $output = new BufferedOutput();
    $this->kernel->getContainer()->set(OutputInterface::class, $output);
  }

  public function testRun(): void {
    $this->setInput(['list']);
    $buffer = $this->runApp();
    $this->assertStringContainsString('Available commands:', $buffer);
  }

  /**
   * @return string
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
