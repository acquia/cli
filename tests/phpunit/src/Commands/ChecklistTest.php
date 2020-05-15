<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Output\Checklist;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Output\ConsoleOutput;

class ChecklistTest extends TestBase {

  protected $output = NULL;

  public function setUp($output = NULL): void {
    $this->output = new ConsoleOutput();
    parent::setUp($this->output);
  }

  public function testSpinner(): void {
    $checklist = new Checklist($this->output);
    $checklist->addItem('Testing!');
    $checklist->completePreviousItem();
    $items = $checklist->getItems();
    $this->assertEquals(1.0, $items[0]["spinner"]->getProgressBar()->getProgressPercent());
    $this->assertEquals('Testing!', $items[0]["message"]);
  }

}
