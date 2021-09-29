<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Output\Checklist;
use Acquia\Cli\Tests\TestBase;
use React\EventLoop\Loop;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

class ChecklistTest extends TestBase {

  protected $output;

  public function setUp($output = NULL): void {
    // Unfortunately this prints to screen. Not sure how else to
    // get the spinner and checklist to work. They require the $output->section()
    // method which is only available for ConsoleOutput. Could make a custom testing
    // output class with the method.
    $this->output = new ConsoleOutput();
    parent::setUp($this->output);
  }

  public function testSpinner(): void {
    putenv('PHPUNIT_RUNNING=1');
    $checklist = new Checklist($this->output);
    $checklist->addItem('Testing!');

    // Make the spinner spin with some output.
    $output_callback = static function ($type, $buffer) use ($checklist) {
      $checklist->updateProgressBar($buffer);
    };
    $this->localMachineHelper->execute(['echo', 'hello world'], $output_callback, NULL, FALSE);

    // Complete the item.
    $checklist->completePreviousItem();
    $items = $checklist->getItems();
    /** @var \Symfony\Component\Console\Helper\ProgressBar $progress_bar */
    $progress_bar = $items[0]['spinner']->getProgressBar();
    $this->assertEquals('Testing!', $progress_bar->getMessage());

    putenv('PHPUNIT_RUNNING');
  }

  public function testLoopTimeout(): void {
    $loop = Loop::get();
    $output = new BufferedOutput();
    $message = 'Waiting for the IDE to be ready. This can take up to 15 minutes...';
    $spinner = LoopHelper::addSpinnerToLoop($loop, $message, $output);
    $timer = LoopHelper::addTimeoutToLoop($loop, .01, $spinner, $output);
    try {
      $loop->run();
    }
    catch (AcquiaCliException $exception) {
      $this->assertEquals('Timed out after 0.01 minutes!', $exception->getMessage());
    }
    // $loop is statically cached by Loop::get();. We don't want the 0.01 minute timer
    // persisting into other tests so we must use Factory::create().
    $loop->cancelTimer($timer);
  }

}
