<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Helpers\SystemTrustStore;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class SystemTrustStoreTest extends TestCase
{
    private const NOW = 2000000000;

    private string $dir;

    private string $systemBundle;

    private string $keychain;

    private string $cacheFile;

    private string $publicRoot;

    private string $trustedCa;

    private string $distrustedCa;

    private string $leaf;

    /**
     * Every command the store ran, in order.
     *
     * @var array<string[]>
     */
    private array $commands = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/acli-trust-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->systemBundle = $this->dir . '/cert.pem';
        $this->keychain = $this->dir . '/System.keychain';
        $this->cacheFile = $this->dir . '/cache/system-ca-bundle.pem';

        $this->publicRoot = self::fixture('public-root');
        $this->trustedCa = self::fixture('trusted-root');
        $this->distrustedCa = self::fixture('distrusted-root');
        $this->leaf = self::fixture('leaf');

        file_put_contents($this->systemBundle, $this->publicRoot);
        touch($this->systemBundle, self::NOW - 1000);
        touch($this->keychain, self::NOW - 1000);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testBundleIsTheSystemBundleFollowedByTrustedKeychainCas(): void
    {
        $other = self::fixture('replacement-root');
        $store = $this->store([$this->trustedCa, $this->distrustedCa, $this->leaf, $other], [$this->trustedCa, $this->leaf, $other]);

        $this->assertSame($this->cacheFile, $store->bundlePath());
        // Exact: distrusted roots and trusted leaves are out, order is kept, and
        // every certificate starts on a line of its own.
        $this->assertSame($this->publicRoot . "\n" . $this->trustedCa . "\n" . $other . "\n", file_get_contents($this->cacheFile));
        $this->assertSame(0600, fileperms($this->cacheFile) & 0777);
    }

    public function testEachCandidateIsJudgedByVerifyCertAgainstSslPolicy(): void
    {
        $this->store([$this->trustedCa], [$this->trustedCa])->bundlePath();

        $this->assertSame(['/usr/bin/security', 'find-certificate', '-a', '-p', $this->keychain], $this->commands[0]);
        $verify = $this->commands[1];
        $candidate = $verify[3];
        $this->assertSame(['/usr/bin/security', 'verify-cert', '-c', $candidate, '-p', 'ssl', '-L', '-l', '-R', 'offline', '-q'], $verify);
        $this->assertCount(2, $this->commands, 'A trusted leaf or non-CA must not even be evaluated');
        $this->assertFileDoesNotExist($candidate, 'The scratch file must be removed');
    }

    public function testLeafCertificatesAreNotEvaluated(): void
    {
        $this->store([$this->leaf], [$this->leaf])->bundlePath();

        $this->assertCount(1, $this->commands);
    }

    public function testEveryKeychainIsRead(): void
    {
        $second = $this->dir . '/Second.keychain';
        touch($second, self::NOW - 1000);
        $other = self::fixture('replacement-root');
        $runner = $this->runner([$this->keychain => [$this->distrustedCa], $second => [$other]], [$other]);

        $path = (new SystemTrustStore($this->cacheFile, $this->systemBundle, [$this->keychain, $second], $runner, static fn (): int => self::NOW))->bundlePath();

        $this->assertSame($this->publicRoot . "\n" . $other . "\n", file_get_contents((string) $path));
    }

    public function testTheScratchFileIsRemovedEvenWhenEvaluationFails(): void
    {
        $candidate = null;
        $runner = function (array $command) use (&$candidate): ?string {
            if ($command[1] === 'find-certificate') {
                return $this->trustedCa;
            }
            $candidate = $command[3];
            throw new LogicException('security crashed');
        };

        try {
            (new SystemTrustStore($this->cacheFile, $this->systemBundle, [$this->keychain], $runner, static fn (): int => self::NOW))->bundlePath();
            $this->fail('The failure must propagate to configure(), which swallows it');
        } catch (LogicException) {
        }

        $this->assertNotNull($candidate);
        $this->assertFileDoesNotExist($candidate);
    }

    public function testAChangeToAnyKeychainRebuildsTheCache(): void
    {
        $second = $this->dir . '/Second.keychain';
        touch($second, self::NOW - 1000);
        $build = fn (): ?string => (new SystemTrustStore($this->cacheFile, $this->systemBundle, [$this->keychain, $second], $this->runner([$this->keychain => [], $second => [$this->trustedCa]], [$this->trustedCa]), static fn (): int => self::NOW))->bundlePath();
        $build();
        touch($this->cacheFile, self::NOW - 100);
        touch($second, self::NOW - 99);
        $this->commands = [];

        $build();

        $this->assertNotSame([], $this->commands);
    }

    public function testAKeychainThatCannotBeReadIsSkipped(): void
    {
        $second = $this->dir . '/Second.keychain';
        touch($second, self::NOW - 1000);
        $runner = $this->runner([$this->keychain => null, $second => [$this->trustedCa]], [$this->trustedCa]);

        $path = (new SystemTrustStore($this->cacheFile, $this->systemBundle, [$this->keychain, $second], $runner, static fn (): int => self::NOW))->bundlePath();

        $this->assertSame($this->publicRoot . "\n" . $this->trustedCa . "\n", file_get_contents((string) $path));
    }

    public function testAMissingKeychainIsSkipped(): void
    {
        $runner = $this->runner([$this->keychain => [$this->trustedCa]], [$this->trustedCa]);

        $path = (new SystemTrustStore($this->cacheFile, $this->systemBundle, [$this->dir . '/missing.keychain', $this->keychain], $runner, static fn (): int => self::NOW))->bundlePath();

        $this->assertSame($this->cacheFile, $path);
        $this->assertNotContains($this->dir . '/missing.keychain', array_merge(...$this->commands));
    }

    public function testNoBundleWhenTheKeychainAddsNothing(): void
    {
        $this->assertNull($this->store([$this->distrustedCa], [])->bundlePath(), 'Without extra roots the default trust store is already right');
        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function testNoBundleWhenTheSystemBundleIsMissing(): void
    {
        unlink($this->systemBundle);

        $this->assertNull($this->store([$this->trustedCa], [$this->trustedCa])->bundlePath());
        $this->assertSame([], $this->commands);
    }

    public function testAFreshCacheIsReusedWithoutReadingTheKeychain(): void
    {
        $this->givenACacheBuiltAt(self::NOW - 10);

        $this->assertSame($this->cacheFile, $this->store([], [])->bundlePath());
        $this->assertSame([], $this->commands);
    }

    public function testACacheExactlyAtMaxAgeIsStillFresh(): void
    {
        $this->givenACacheBuiltAt(self::NOW - SystemTrustStore::MAX_AGE_SECONDS);

        $this->assertSame($this->cacheFile, $this->store([], [])->bundlePath());
        $this->assertSame([], $this->commands);
    }

    public function testAStaleCacheWhoseRootIsGoneIsRemoved(): void
    {
        $this->givenACacheBuiltAt(self::NOW - SystemTrustStore::MAX_AGE_SECONDS - 1);

        $this->assertNull($this->store([], [])->bundlePath());
        $this->assertFileDoesNotExist($this->cacheFile, 'A root removed from the keychain must stop being trusted');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSources(): array
    {
        return [
            'keychain' => ['keychain'],
            'system bundle' => ['systemBundle'],
        ];
    }

    #[DataProvider('providerSources')]
    public function testTheCacheIsRebuiltWhenASourceChanges(string $source): void
    {
        $this->givenACacheBuiltAt(self::NOW - 100);
        touch($this->{$source}, self::NOW - 99);
        $other = self::fixture('replacement-root');

        $this->store([$other], [$other])->bundlePath();

        $this->assertSame($this->publicRoot . "\n" . $other . "\n", file_get_contents($this->cacheFile));
    }

    #[DataProvider('providerSources')]
    public function testASourceTouchedInTheSameSecondAsTheBuildKeepsTheCache(string $source): void
    {
        $this->givenACacheBuiltAt(self::NOW - 100);
        touch($this->{$source}, self::NOW - 100);

        $this->store([], [])->bundlePath();

        $this->assertSame([], $this->commands);
    }

    /**
     * @return array<string, array{array<string, string|false>, string, string, bool}>
     */
    public static function providerShouldApply(): array
    {
        return [
            'native Linux' => [[], 'micro', 'Linux', false],
            'native macOS, nothing set' => [[], 'micro', 'Darwin', true],
            'PHAR on system PHP' => [[], 'cli', 'Darwin', false],
            'SSL_CERT_DIR already set' => [['SSL_CERT_DIR' => '/certs'], 'micro', 'Darwin', false],
            'SSL_CERT_FILE already set' => [['SSL_CERT_FILE' => '/x.pem'], 'micro', 'Darwin', false],
        ];
    }

    /**
     * @param array<string, string|false> $env
     */
    #[DataProvider('providerShouldApply')]
    public function testShouldApply(array $env, string $sapi, string $os, bool $expected): void
    {
        $getenv = static fn (string $name): string|false => $env[$name] ?? false;
        $this->assertSame($expected, SystemTrustStore::shouldApply($sapi, $os, $getenv));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerTlsHint(): array
    {
        return [
            'bad CA file' => ['cURL error 77: error setting certificate file: /nope.pem', true],
            'dns' => ['cURL error 6: Could not resolve host: id.acquia.com', false],
            'error 600 is not error 60' => ['cURL error 600: something else', false],
            'timeout' => ['cURL error 28: Operation timed out after 15000 milliseconds', false],
            'untrusted issuer' => ['cURL error 60: SSL certificate OpenSSL verify result: unable to get local issuer certificate (20)', true],
        ];
    }

    #[DataProvider('providerTlsHint')]
    public function testTlsHint(string $message, bool $expectHint): void
    {
        $hint = SystemTrustStore::tlsHint($message);
        if (!$expectHint) {
            $this->assertNull($hint);
            return;
        }
        $this->assertSame("Acquia CLI could not verify the server's TLS certificate. This usually means a proxy or security tool on your network is inspecting TLS and re-signing certificates with your organization's root certificate. Set SSL_CERT_FILE to a PEM bundle that includes that root, then try again.", $hint);
    }

    private function givenACacheBuiltAt(int $time): void
    {
        $this->store([$this->trustedCa], [$this->trustedCa])->bundlePath();
        touch($this->cacheFile, $time);
        touch($this->keychain, $time - 1);
        touch($this->systemBundle, $time - 1);
        $this->commands = [];
    }

    /**
     * @param string[] $keychainCerts
     *   What `security find-certificate` returns.
     * @param string[] $trusted
     *   Which certificates `security verify-cert` accepts.
     */
    private function store(array $keychainCerts, array $trusted): SystemTrustStore
    {
        return new SystemTrustStore($this->cacheFile, $this->systemBundle, [$this->keychain], $this->runner([$this->keychain => $keychainCerts], $trusted), static fn (): int => self::NOW);
    }

    /**
     * @param array<string, string[]|null> $keychains
     *   Certificates per keychain; null means reading it fails.
     * @param string[] $trusted
     */
    private function runner(array $keychains, array $trusted): \Closure
    {
        return function (array $command) use ($keychains, $trusted): ?string {
            $this->commands[] = $command;
            if ($command[1] === 'find-certificate') {
                if (!array_key_exists($command[4], $keychains)) {
                    throw new LogicException('Unexpected keychain ' . $command[4]);
                }
                return $keychains[$command[4]] === null ? null : implode("\n", $keychains[$command[4]]);
            }
            if ($command[1] === 'verify-cert') {
                return in_array(file_get_contents($command[3]), $trusted, true) ? '' : null;
            }
            throw new LogicException('Unexpected command ' . implode(' ', $command));
        };
    }

    private static function fixture(string $name): string
    {
        // Trimmed, as `security find-certificate` output is split into bare PEM blocks.
        return trim((string) file_get_contents(dirname(__DIR__, 3) . '/fixtures/trust-store/' . $name . '.pem'));
    }
}
