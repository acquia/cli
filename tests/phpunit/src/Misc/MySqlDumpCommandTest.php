<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\CommandBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The streamed push dumps plain SQL to stdout rather than a gzipped file, and
 * the flag set is version-sensitive: --set-gtid-purged does not exist in the
 * MariaDB client and --skip-masking-policies only in newer MySQL ones, and
 * passing either to a client that lacks it is a hard error. So the flags are
 * selected from what the local client advertises, never assumed.
 */
#[CoversClass(CommandBase::class)]
class MySqlDumpCommandTest extends TestCase
{
    private const HELP_MODERN_MYSQL = '
  --set-gtid-purged[=name] Add "SET @@GLOBAL.GTID_PURGED" to the output.
  --skip-masking-policies Do not dump masking policies.
  --single-transaction  Creates a consistent snapshot.
  --no-tablespaces      Do not dump any tablespace information.
';

    private const HELP_MARIADB = '
  --single-transaction  Creates a consistent snapshot.
  --no-tablespaces      Do not dump any tablespace information.
  --hex-blob            Dump binary strings in hexadecimal format.
';

    public function testBaselineFlagsAreAlwaysPresent(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);

        $this->assertSame('mysqldump', $command[0]);
        foreach (['--single-transaction', '--no-tablespaces', '--default-character-set=utf8mb4', '--hex-blob'] as $flag) {
            $this->assertContains($flag, $command, "Baseline flag $flag must always be passed.");
        }
    }

    /**
     * Confirmed empirically: omitting it fails the import with
     * "ERROR 3546 @@GLOBAL.GTID_PURGED cannot be changed".
     */
    public function testGtidPurgedIsDisabledWhenTheClientSupportsIt(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);

        $this->assertContains('--set-gtid-purged=OFF', $command);
    }

    public function testGtidPurgedIsOmittedForMariadbClients(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MARIADB);

        $this->assertNotContains('--set-gtid-purged=OFF', $command, 'The MariaDB client rejects this option outright.');
    }

    public function testMaskingPoliciesSkippedOnlyWhenSupported(): void
    {
        $modern = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);
        $older = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MARIADB);

        $this->assertContains('--skip-masking-policies', $modern);
        $this->assertNotContains('--skip-masking-policies', $older);
    }

    /**
     * Triggers survive the restricted client, so suppressing them would lose
     * data for no reason.
     */
    public function testTriggersAreNotSkipped(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);

        $this->assertNotContains('--skip-triggers', $command);
        $this->assertNotContains('--no-create-info', $command);
    }

    public function testDatabaseNameIsTheFinalPositionalArgument(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);

        $this->assertSame('drupal', end($command), 'mysqldump takes the database as a trailing positional argument.');
    }

    /**
     * The dump must stream, so no output redirection and no compression.
     */
    public function testDumpIsUncompressedAndUnredirected(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);
        $joined = implode(' ', $command);

        $this->assertStringNotContainsString('>', $joined);
        $this->assertStringNotContainsString('gzip', $joined);
        $this->assertStringNotContainsString('|', $joined);
    }

    /**
     * The password goes through the process environment as MYSQL_PWD; it must
     * never appear in argv, where the process table would expose it.
     */
    public function testPasswordIsNeverAnArgument(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', 'drupal', self::HELP_MODERN_MYSQL);

        // Checked per argument, not against the joined string: "-p" is a
        // substring of --skip-masking-policies and --set-gtid-purged.
        foreach ($command as $argument) {
            $this->assertIsString($argument, 'Every element must be a plain string for argv execution.');
            $this->assertDoesNotMatchRegularExpression('/^(-p|--password)/', $argument, 'The password must never be an argument.');
        }
    }

    /**
     * mysqldump reads a bare trailing argument through my_getopt, so a database
     * name beginning with a hyphen would be taken as an option. An end-of-
     * options marker costs nothing and removes the question.
     */
    public function testDatabaseNameIsSeparatedFromOptions(): void
    {
        $command = CommandBase::buildStreamingMySqlDumpCommand('db.example', 'dbuser', '--print-defaults', self::HELP_MODERN_MYSQL);

        $this->assertSame('--print-defaults', end($command));
        $this->assertSame('--', $command[count($command) - 2], 'The database name must follow an end-of-options marker.');
    }
}
