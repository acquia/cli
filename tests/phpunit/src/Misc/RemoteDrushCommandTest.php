<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The remote command string is validated by the restricted SSH policy that
 * Trial environments enforce. It accepts at most one `cd <docroot>;` prefix
 * followed by a single `drush` invocation, requires the docroot to sit under
 * /var/www/html,
 * and — for sql:cli specifically — rejects any option after the subcommand.
 * These are correctness properties, not formatting preferences: `--uri` placed
 * after `sql:cli` is denied outright. This test exists so a future edit to the
 * argument order fails here rather than silently on a trial environment.
 */
#[CoversClass(CommandBase::class)]
class RemoteDrushCommandTest extends TestCase
{
    private const ALIAS = 'site.dev';

    private const DOMAIN = 'sitedev.hosted.acquia-sites.com';

    public function testBuildsCdPrefixAndDrushInvocation(): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, self::DOMAIN, ['status', '--fields=db-status']);

        $this->assertSame([
            'cd /var/www/html/site.dev/docroot; ',
            'drush',
            '--uri=http://sitedev.hosted.acquia-sites.com status --fields=db-status',
        ], $command);
    }

    public function testOmitsUriWhenNoDefaultDomain(): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, null, ['status']);

        $this->assertSame([
            'cd /var/www/html/site.dev/docroot; ',
            'drush',
            'status',
        ], $command);
    }

    /**
     * A user-supplied --uri must still win, which array_unshift guarantees by
     * placing the default first.
     */
    public function testUserSuppliedUriTakesPrecedence(): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, self::DOMAIN, ['--uri=http://override.example', 'status']);

        $this->assertStringEndsWith('--uri=http://override.example status', $command[2]);
    }

    /**
     * @return array<string, array{array<string>}>
     */
    public static function providerPolicyInvariants(): array
    {
        return [
            'cache rebuild' => [['cache:rebuild']],
            'sql:cli' => [['sql:cli']],
            'status' => [['status', '--fields=db-status']],
        ];
    }

    #[DataProvider('providerPolicyInvariants')]
    public function testSatisfiesTrialPolicyInvariants(array $drushArguments): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, self::DOMAIN, $drushArguments);
        $joined = implode('', $command);

        // Exactly one command separator: ValidateExec strips a single leading
        // "cd <path>;" and denies any further ";" token.
        $this->assertSame(1, substr_count($joined, ';'), 'Remote command must contain exactly one command separator.');

        // The cd target must sit under the only permitted Drupal root.
        $this->assertMatchesRegularExpression('#^cd /var/www/html/[^/]+/docroot; $#', $command[0]);

        $this->assertSame('drush', $command[1]);
    }

    /**
     * sql:cli accepts only value-less global options after the subcommand, so
     * --uri has to precede it.
     */
    public function testUriPrecedesSqlCliSubcommand(): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, self::DOMAIN, ['sql:cli']);
        $arguments = explode(' ', $command[2]);

        $uriIndex = array_search('--uri=http://' . self::DOMAIN, $arguments, true);
        $subcommandIndex = array_search('sql:cli', $arguments, true);

        $this->assertIsInt($uriIndex);
        $this->assertIsInt($subcommandIndex);
        $this->assertLessThan($subcommandIndex, $uriIndex, '--uri must precede sql:cli; the Trial policy denies it afterwards.');
        $this->assertSame($subcommandIndex, array_key_last($arguments), 'Nothing may follow sql:cli.');
    }

    /**
     * The policy denies any option after `sql:cli`, but that restriction
     * belongs to the streamed push, not to `remote:drush`. On an unrestricted
     * environment `drush sql:cli --database=foo` is legitimate and useful, so
     * the shared builder must not reject it — only the push path is
     * constrained, and it is constrained structurally by passing exactly
     * `['sql:cli']`.
     */
    public function testRemoteDrushKeepsOptionsAfterSqlCli(): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, self::DOMAIN, ['sql:cli', '--database=foo']);

        $this->assertStringContainsString('sql:cli --database=foo', $command[2]);
    }

    /**
     * The streamed push cannot pass anything after the subcommand, because it
     * does not take arguments at all.
     */
    public function testStreamedSqlCliCommandTakesNoArguments(): void
    {
        $command = CommandBase::buildStreamedSqlCliCommand(self::ALIAS, self::DOMAIN);
        $arguments = explode(' ', $command[2]);

        $this->assertSame('sql:cli', end($arguments), 'Nothing may follow sql:cli on the streamed path.');
    }

    /**
     * Other subcommands are unaffected: the restriction belongs to sql:cli.
     */
    public function testOtherSubcommandsKeepTheirOptions(): void
    {
        $command = CommandBase::buildRemoteDrushCommand(self::ALIAS, self::DOMAIN, ['status', '--fields=db-status']);

        $this->assertStringContainsString('status --fields=db-status', $command[2]);
    }

    /**
     * Argument vectors remove the *local* quoting surface, not the remote one:
     * ssh joins its arguments with spaces and the far side parses the result as
     * a command line. On a restricted environment the restricted environment's own parser catches
     * a stray `;`, but the streamed transport also runs against unrestricted
     * environments — a Windows client, for instance — where nothing does. Both
     * values come from the Cloud API, so validate them here, at the one point
     * every caller shares.
     *
     * @return array<string, array{string}>
     */
    public static function providerUnsafeAlias(): array
    {
        return [
            'backtick' => ['site`id`.dev'],
            'command substitution' => ['site$(id).dev'],
            'double quote' => ['site".dev'],
            'newline' => ["site.dev\nid"],
            'pipe' => ['site.dev|id'],
            'semicolon' => ['site.dev; id'],
            'single quote' => ["site'.dev"],
            'space' => ['site dev'],
            'trailing newline' => ["site.dev\n"],
        ];
    }

    #[DataProvider('providerUnsafeAlias')]
    public function testRejectsUnsafeAlias(string $alias): void
    {
        $this->expectException(AcquiaCliException::class);
        CommandBase::buildStreamedSqlCliCommand($alias, self::DOMAIN);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnsafeDomain(): array
    {
        return [
            'command substitution' => ['example.com$(id)'],
            'semicolon' => ['example.com; id'],
            'space' => ['example.com id'],
            'trailing newline' => ["example.com\n"],
        ];
    }

    #[DataProvider('providerUnsafeDomain')]
    public function testRejectsUnsafeDefaultDomain(string $domain): void
    {
        $this->expectException(AcquiaCliException::class);
        CommandBase::buildStreamedSqlCliCommand(self::ALIAS, $domain);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function providerSafeIdentifiers(): array
    {
        return [
            'hyphenated sitegroup' => ['my-site.prod', 'my-site.example.com'],
            'no domain' => ['site.dev', null],
            'typical' => ['profserv2.01dev', 'profserv201dev.enterprise-g1.acquia-sites.com'],
            'underscored' => ['my_site.dev', 'example.com'],
        ];
    }

    #[DataProvider('providerSafeIdentifiers')]
    public function testAcceptsRealIdentifiers(string $alias, ?string $domain): void
    {
        $command = CommandBase::buildStreamedSqlCliCommand($alias, $domain);

        $this->assertSame("cd /var/www/html/$alias/docroot; ", $command[0]);
    }
}
