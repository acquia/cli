<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Output\Checklist;
use Acquia\Cli\Tests\TestBase;
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
    $checklist = new Checklist($this->output);
    $checklist->addItem('Testing!');

    // Make the spinner spin with some output.
    $output_callback = static function ($type, $buffer) use ($checklist) {
      $checklist->updateProgressBar($buffer);
    };
    $this->application->getLocalMachineHelper()->execute(['echo', 'hello world'], $output_callback, NULL, FALSE);

    // Complete the item.
    $checklist->completePreviousItem();
    $items = $checklist->getItems();
    /** @var \Symfony\Component\Console\Helper\ProgressBar $progress_bar */
    $progress_bar = $items[0]['spinner']->getProgressBar();
    $this->assertEquals('Testing!', $progress_bar->getMessage());
    $this->assertEquals(1.0, $progress_bar->getProgressPercent());
  }

}
