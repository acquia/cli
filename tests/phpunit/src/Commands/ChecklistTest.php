<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Output\Checklist;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ChecklistTest extends TestBase {

  protected OutputInterface $output;

  public function setUp(): void {
    // Unfortunately this prints to screen. Not sure how else to
    // get the spinner and checklist to work. They require the $output->section()
    // method which is only available for ConsoleOutput. Could make a custom testing
    // output class with the method.
    parent::setUp();
    $this->output = new ConsoleOutput();
  }

  public function testSpinner(): void {
    putenv('PHPUNIT_RUNNING=1');
    $checklist = new Checklist($this->output);
    $checklist->addItem('Testing!');

    // Make the spinner spin with some output.
    $outputCallback = static function (string $type, string $buffer) use ($checklist): void {
      $checklist->updateProgressBar($buffer);
    };
    $this->localMachineHelper->execute(['echo', 'hello world'], $outputCallback, NULL, FALSE);

    // Complete the item.
    $checklist->completePreviousItem();
    $items = $checklist->getItems();
    /** @var \Symfony\Component\Console\Helper\ProgressBar $progressBar */
    $progressBar = $items[0]['spinner']->getProgressBar();
    $this->assertEquals('Testing!', $progressBar->getMessage());
    $this->assertEquals('<info>✔</info>', $progressBar->getBarCharacter());
    $this->assertEquals('[38;5;202m⢸[0m', $progressBar->getProgressCharacter());
    $this->assertEquals('⌛', $progressBar->getEmptyBarCharacter());
    $this->assertEquals(1, $progressBar->getBarWidth());
    $this->assertEquals('', $progressBar->getMessage('detail'));

    putenv('PHPUNIT_RUNNING');
  }

}
