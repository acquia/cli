<?php

namespace Acquia\Ads\Tests;

use Acquia\Ads\AdsApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Class BltTestBase.
 *
 * Base class for all tests that are executed for BLT itself.
 */
abstract class CommandTestBase extends TestCase
{

    /** @var Application */
    protected $application;

    /**
     * {@inheritdoc}
     *
     * @see https://symfony.com/doc/current/console.html#testing-commands
     */
    public function setUp(): void
    {
        parent::setUp();

        $input = new ArrayInput([]);
        $output = new NullOutput();
        $logger = new ConsoleLogger($output);
        $repo_root = null;
        $this->application = new AdsApplication('ads', 'UNKNOWN', $input, $output, $logger, $repo_root);
    }

    /**
     * Removes temporary file.
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }
}
