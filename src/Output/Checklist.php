<?php

namespace Acquia\Ads\Output;

use Acquia\Ads\Output\Spinner\Spinner;
use Symfony\Component\Console\Output\ConsoleOutput;

class Checklist
{
    /** @var array */
    private $items = [];
    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    private $output;

    /**
     * Checklist constructor.
     *
     * @param \Symfony\Component\Console\Output\ConsoleOutput $output
     */
    public function __construct(ConsoleOutput $output)
    {
        $this->output = $output;
    }

    public function addItem($message): void
    {
        $spinner = new Spinner($this->output, 4);

        $this->items[] = [
          'message' => $message,
          'spinner' => $spinner,
        ];
        $spinner->setMessage($message . '...');
        $spinner->start();
    }

    /**
     */
    public function completePreviousItem(): void
    {
        $item = $this->getLastItem();
        /** @var Spinner $spinner */
        $spinner = $item['spinner'];
        $spinner->setMessage($item['message']);
        $spinner->finish();
    }

    protected function getLastItem() {
        return end($this->items);
    }

    public function updateProgressBar($update_message): void
    {
        $item = $this->getLastItem();
        /** @var Spinner $spinner */
        $spinner = $item['spinner'];
        $message_lines = explode(PHP_EOL, $update_message);
        foreach ($message_lines as $line) {
            $spinner->advance();
            // @todo Replace this with logger.
            if ($this->output->isVeryVerbose()) {
                $this->output->writeln($line);
            }
        }
    }
}
