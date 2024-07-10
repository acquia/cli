<?php

declare(strict_types=1);

namespace Acquia\Cli\Output;

use Acquia\Cli\Output\Spinner\Spinner;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Checklist
{
    /**
     * @var array<mixed>
     */
    private array $items = [];

    private int $indentLength = 4;

    /**
     * Checklist constructor.
     */
    public function __construct(private OutputInterface $output)
    {
    }

    public function addItem(string $message): void
    {
        $item = ['message' => $message];

        if ($this->useSpinner()) {
            $spinner = new Spinner($this->output, $this->indentLength);
            $spinner->setMessage($message . '...');
            $spinner->start();
            $item['spinner'] = $spinner;
        }

        $this->items[] = $item;
    }

    public function completePreviousItem(): void
    {
        if ($this->useSpinner()) {
            $item = $this->getLastItem();
            /** @var \Acquia\Cli\Output\Spinner\Spinner $spinner */
            $spinner = $item['spinner'];
            $spinner->setMessage(' ', 'detail');
            $spinner->setMessage($item['message']);
            $spinner->advance();
            $spinner->finish();
        }
    }

    private function getLastItem(): mixed
    {
        return end($this->items);
    }

    public function updateProgressBar(string $updateMessage): void
    {
        $item = $this->getLastItem();
        if (!$item) {
            return;
        }
        if ($this->useSpinner()) {
            /** @var \Acquia\Cli\Output\Spinner\Spinner $spinner */
            $spinner = $item['spinner'];
        }

        $messageLines = explode(PHP_EOL, $updateMessage);
        foreach ($messageLines as $line) {
            if (isset($spinner) && $item['spinner']) {
                if (trim($line)) {
                    $spinner->setMessage(str_repeat(' ', $this->indentLength * 2) . $line, 'detail');
                }
                $spinner->advance();
            }
        }
        // Ensure that the new message is displayed at least once. Sometimes it is
        // not displayed if the minimum redraw frequency is not met.
        if (isset($spinner) && $item['spinner']) {
            $spinner->getProgressBar()->display();
        }
    }

    private function useSpinner(): bool
    {
        return $this->output instanceof ConsoleOutput
        && (getenv('CI') !== 'true' || getenv('PHPUNIT_RUNNING'));
    }

    /**
     * @return array<mixed>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
