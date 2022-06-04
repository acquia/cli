<?php


namespace Acquia\Cli\Command\Utitlity;

class CodeStudioUtitlity
{
    /**
     * A timer to calculate $this->duration.
     *
     * @var \SebastianBergmann\Timer\Timer
     */
    private $timer;

    /**
     * The time between the creation of the log and the writing of it to file.
     *
     * @var string
     */
    private $duration;

    /**
     * Initial and start the timer.
     */
    public function __construct() {
        $this->timer = new Timer();
        $this->timer->start();
    }

    /**
     * Stops the timer, sets $this->duration.
     */
    public function stopTimer() {
//        if (!isset($this->duration)) {
//            $this->duration = $this->timer->stop()->asSeconds();
//        }
//        return $this->duration;
    }
}