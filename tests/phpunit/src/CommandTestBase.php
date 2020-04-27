<?php

namespace Acquia\Ads\Tests;

use Acquia\Ads\AdsApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class BltTestBase.
 *
 * Base class for all tests that are executed for BLT itself.
 */
abstract class CommandTestBase extends TestCase
{

    /**
     * The command tester.
     *
     * @var \Symfony\Component\Console\Tester\CommandTester
     */
    private $commandTester;
    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    private $consoleOutput;
    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * Creates a command object to test.
     *
     * @return \Symfony\Component\Console\Command\Command
     *   A command object with mocked dependencies injected.
     */
    abstract protected function createCommand(): Command;

    /** @var Application */
    protected $application;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $this->consoleOutput = new ConsoleOutput();
        $this->fs = new Filesystem();
        $this->printTestName();

        parent::setUp();
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
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function executeCommand(array $args = [], array $inputs = []): void
    {
        chdir(__DIR__ . '/../../fixtures/project');
        $tester = $this->getCommandTester();
        $tester->setInputs($inputs);
        $command_name = $this->createCommand()::getDefaultName();
        $args = array_merge(['command' => $command_name], $args);
        $tester->execute($args, ['verbosity' => Output::VERBOSITY_VERBOSE]);
    }

    /**
     * Gets the command tester.
     *
     * @return \Symfony\Component\Console\Tester\CommandTester
     *   A command tester.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getCommandTester(): CommandTester
    {
        if ($this->commandTester) {
            return $this->commandTester;
        }

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $logger = new ConsoleLogger($output);
        $repo_root = null;
        $this->application = new AdsApplication('ads', 'UNKNOWN', $input, $output, $logger, $repo_root);
        $created_command = $this->createCommand();
        $this->application->add($created_command);
        $found_command = $this->application->find($created_command::getDefaultName());
        $this->assertInstanceOf(get_class($created_command), $found_command, 'Instantiated class.');
        $this->commandTester = new CommandTester($found_command);

        return $this->commandTester;
    }

    /**
     * Gets the display returned by the last execution of the command.
     *
     * @return string
     *   The display.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getDisplay(): string
    {
        return $this->getCommandTester()->getDisplay();
    }

    /**
     * Gets the status code returned by the last execution of the command.
     *
     * @return int
     *   The status code.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getStatusCode(): int
    {
        return $this->getCommandTester()->getStatusCode();
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void
    {
        print "\n";
        print $this->getDisplay();
    }

    /**
     * Write full width line.
     *
     * @param string $message
     *   Message.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   Output.
     */
    protected function writeFullWidthLine($message, OutputInterface $output): void
    {
        $terminal_width = (new Terminal())->getWidth();
        $padding_len = ($terminal_width - strlen($message)) / 2;
        $pad = $padding_len > 0 ? str_repeat('-', $padding_len) : '';
        $output->writeln("<comment>{$pad}{$message}{$pad}</comment>");
    }

    /**
     *
     */
    protected function printTestName(): void
    {
            $this->consoleOutput->writeln("");
            $this->writeFullWidthLine(
                get_class($this) . "::" . $this->getName(),
                $this->consoleOutput
            );
    }
}
