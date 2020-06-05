<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\Api\ApiCommandHelper;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Exception;
use PhpCoveralls\Component\Log\ConsoleLogger;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\CommandTester;
use Webmozart\PathUtil\Path;

/**
 * Class CommandTestBase.
 * @property \Acquia\Cli\Command\CommandBase $command
 */
abstract class CommandTestBase extends TestBase {

  /**
   * The command tester.
   *
   * @var \Symfony\Component\Console\Tester\CommandTester
   */
  private $commandTester;

  /**
   * @var \Acquia\Cli\Command\CommandBase
   */
  protected $command;

  /**
   * @var string
   */
  protected $targetGitConfigFixture;

  /**
   * @var string
   */
  protected $sourceGitConfigFixture;

  /**
   * Creates a command object to test.
   *
   * @return \Symfony\Component\Console\Command\Command
   *   A command object with mocked dependencies injected.
   */
  abstract protected function createCommand(): Command;

  /**
   * This method is called before each test.
   *
   * @param OutputInterface $output
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function setUp($output = NULL): void {
    parent::setUp();
    if (!isset($this->command)) {
      $this->command = $this->createCommand();
    }
    $this->printTestName();
  }

  protected function setCommand(Command $command): void {
    $this->command = $command;
  }

  /**
   * Executes a given command with the command tester.
   *
   * @param array $args
   *   The command arguments.
   * @param string[] $inputs
   *   An array of strings representing each input passed to the command input
   *   stream.
   *
   * @throws \Exception
   */
  protected function executeCommand(array $args = [], array $inputs = []): void {
    $cwd = $this->projectFixtureDir;
    chdir($cwd);

    $this->commandTester = $this->createCommandTester();
    $this->commandTester->setInputs($inputs);

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln('');
      $this->consoleOutput->writeln('Executing <comment>' . $this->command->getName() . '</comment> in ' . $cwd);
      $this->consoleOutput->writeln('<comment>------Begin command output-------</comment>');
    }

    try {
      $this->commandTester->execute($args, ['verbosity' => Output::VERBOSITY_VERBOSE]);
    }
    catch (Exception $e) {if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln($this->commandTester->getDisplay());
      }
      throw $e;
    }

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln($this->commandTester->getDisplay());
      $this->consoleOutput->writeln('<comment>------End command output---------</comment>');
      $this->consoleOutput->writeln('');
    }
  }

  /**
   * Gets the command tester.
   *
   * @return \Symfony\Component\Console\Tester\CommandTester
   *   A command tester.
   */
  protected function getCommandTester(): CommandTester {
    return $this->commandTester;
  }

  /**
   * @param $args
   *
   * @param $application
   *
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  protected function createCommandTester(): CommandTester {
    if (!isset($this->command)) {
      $this->command = $this->createCommand();
    }

    $this->application->add($this->command);
    $found_command = $this->application->find($this->command->getName());
    $this->assertInstanceOf(get_class($this->command), $found_command, 'Instantiated class.');

    return new CommandTester($found_command);
  }

  /**
   * Gets the display returned by the last execution of the command.
   *
   * @return string
   *   The display.
   */
  protected function getDisplay(): string {
    return $this->getCommandTester()->getDisplay();
  }

  /**
   * Gets the status code returned by the last execution of the command.
   *
   * @return int
   *   The status code.
   */
  protected function getStatusCode(): int {
    return $this->getCommandTester()->getStatusCode();
  }

  /**
   * Write full width line.
   *
   * @param string $message
   *   Message.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   */
  protected function writeFullWidthLine($message, OutputInterface $output): void {
    $terminal_width = (new Terminal())->getWidth();
    $padding_len = ($terminal_width - strlen($message)) / 2;
    $pad = $padding_len > 0 ? str_repeat('-', $padding_len) : '';
    $output->writeln("<comment>{$pad}{$message}{$pad}</comment>");
  }

  /**
   * Prints the name of the PHPUnit test to output.
   */
  protected function printTestName(): void {
    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln("");
      $this->writeFullWidthLine(get_class($this) . "::" . $this->getName(), $this->consoleOutput);
    }
  }

  /**
   * Creates a mock .git/config.
   */
  protected function createMockGitConfigFile(): void {
    // Create mock git config file.
    $this->sourceGitConfigFixture = Path::join($this->fixtureDir, 'git_config');
    $this->targetGitConfigFixture = Path::join($this->fixtureDir, 'project', '.git', 'config');
    $this->fs->remove([$this->targetGitConfigFixture]);
    $this->fs->copy($this->sourceGitConfigFixture, $this->targetGitConfigFixture);
  }

  /**
   * Remove mock .git/config.
   */
  protected function removeMockGitConfig(): void {
    $this->fs->remove([$this->targetGitConfigFixture, dirname($this->targetGitConfigFixture)]);
  }

  /**
   * Create a mock LocalMachineHelper.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockLocalMachineHelper(): ObjectProphecy {
    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);

    return $local_machine_helper;
  }

}
