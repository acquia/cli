<?php

namespace Acquia\Cli\Tests;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\TesterTrait;

/**
 * Class CommandTester.
 *
 * The primary difference between this class and Symfony's CommandTester is that this class accepts an
 * InputInterface object for $input in execute() rather than accepting an array of arguments and creating
 * a separate $input. This makes it simpler to configure the Acquia CLI container with an $input and re-use
 * the exact same $input when testing commands.
 */
class CommandTester {

  use TesterTrait;

  private $command;
  private $input;
  private $statusCode;

  public function __construct(Command $command) {
    $this->command = $command;
  }

  public function execute(InputInterface $input, array $options = []): int {
    $this->input = $input;
    // Use an in-memory input stream even if no inputs are set so that QuestionHelper::ask() does not rely on the blocking STDIN.
    $this->input->setStream(self::createStream($this->inputs));

    if (isset($options['interactive'])) {
      $this->input->setInteractive($options['interactive']);
    }

    if (!isset($options['decorated'])) {
      $options['decorated'] = FALSE;
    }

    $this->initOutput($options);

    return $this->statusCode = $this->command->run($this->input, $this->output);
  }

}
