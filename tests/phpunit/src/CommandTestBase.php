<?php

namespace Acquia\Cli\Tests;

use Symfony\Component\Console\Command\Command;
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
  protected $command;
  /** @var string */
  protected $targetGitConfigFixture;
  /** @var string */
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
   */
  protected function executeCommand(array $args = [], array $inputs = []): void {
    $cwd = $this->projectFixtureDir;
    chdir($cwd);
    $tester = $this->getCommandTester();
    $tester->setInputs($inputs);
    $command_name = $this->command->getName();
    $args = array_merge(['command' => $command_name], $args);

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln('');
      $this->consoleOutput->writeln('Executing <comment>' . $this->command->getName() . '</comment> in ' . $cwd);
      $this->consoleOutput->writeln('<comment>------Begin command output-------</comment>');
    }

    try {
      $tester->execute($args, ['verbosity' => Output::VERBOSITY_VERBOSE]);
    }
    catch (\Exception $e) {if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
        print $this->getDisplay();
      }
      throw $e;
    }

    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln($tester->getDisplay());
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
    if ($this->commandTester) {
      return $this->commandTester;
    }

    if (!isset($this->command)) {
      $this->command = $this->createCommand();
    }

    $this->application->add($this->command);
    $found_command = $this->application->find($this->command->getName());
    $this->assertInstanceOf(get_class($this->command), $found_command, 'Instantiated class.');
    $this->commandTester = new CommandTester($found_command);

    return $this->commandTester;
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
   *
   * @throws \Psr\Cache\InvalidArgumentException
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
   *
   */
  protected function printTestName(): void {
    if (getenv('ACLI_PRINT_COMMAND_OUTPUT')) {
      $this->consoleOutput->writeln("");
      $this->writeFullWidthLine(get_class($this) . "::" . $this->getName(), $this->consoleOutput);
    }
  }

  /**
   *
   */
  protected function createMockGitConfigFile(): void {
    // Create mock git config file.
    $this->sourceGitConfigFixture = Path::join($this->fixtureDir, 'git_config');
    $this->targetGitConfigFixture = Path::join($this->fixtureDir, 'project', '.git', 'config');
    $this->fs->remove([$this->targetGitConfigFixture]);
    $this->fs->copy($this->sourceGitConfigFixture, $this->targetGitConfigFixture);
  }

  /**
   *
   */
  protected function removeMockGitConfig(): void {
    $this->fs->remove([$this->targetGitConfigFixture, dirname($this->targetGitConfigFixture)]);
  }

}
