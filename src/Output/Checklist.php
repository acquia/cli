<?php

namespace Acquia\Ads\Output;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class Checklist
{
    /**
     * @var \Symfony\Component\Console\Output\ConsoleSectionOutput
     */
    private $section;

    /** @var array */
    private $items = [];
    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    private $progressBar;

    /**
     * Checklist constructor.
     *
     * @param \Symfony\Component\Console\Output\ConsoleOutput $output
     */
    public function __construct(ConsoleOutput $output)
    {
        $this->section = $output->section();
        $this->progressBar = new ProgressBar();
    }

    public function addItem($message): void
    {
        $this->section->writeln($this->getIndent() . $message . '...');
        $this->items[] = $message;
    }

    /**
     */
    public function completePreviousItem(): void
    {
        $this->section->clear(1);
        $this->section->writeln($this->getIndent() . '<info>✔</info> ' . end($this->items));
    }

    protected function getIndent(): string
    {
        return str_repeat(' ', 4);
    }

    public function streamProgressMessage() {

    }
}
