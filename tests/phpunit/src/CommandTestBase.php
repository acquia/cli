<?php

namespace Acquia\Ads\Tests;

use Acquia\Ads\AdsApplication;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Class BltTestBase.
 *
 * Base class for all tests that are executed for BLT itself.
 */
abstract class CommandTestBase extends \PHPUnit_Framework_TestCase
{

    /** @var Application */
    protected $application;

    /**
     * {@inheritdoc}
     *
     * @see https://symfony.com/doc/current/console.html#testing-commands
     */
    public function setUp()
    {
        parent::setUp();

        $input = new ArrayInput([]);
        $output = new NullOutput();
        $logger = new ConsoleLogger($output);
        $repo_root = '';
        $this->application = new AdsApplication('ads', UNKNOWN, $input, $output, $logger, $repo_root);
    }

    /**
     * Removes temporary file.
     */
    public function tearDown()
    {
        parent::tearDown();
    }
}
