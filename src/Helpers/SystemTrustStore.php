<?php

declare(strict_types=1);

namespace Acquia\Cli\Helpers;

use Closure;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Makes the native macOS binary trust what macOS trusts.
 *
 * The native build links its own OpenSSL, which reads only /etc/ssl/cert.pem.
 * Organizations that inspect TLS (any vendor, or an in-house CA) install their
 * root in the macOS keychain, which that OpenSSL never reads, so every HTTPS
 * request fails with cURL error 60. This builds a bundle from /etc/ssl/cert.pem
 * plus the keychain certificates macOS itself trusts for SSL, and points
 * OpenSSL at it through SSL_CERT_FILE — its own, documented override.
 *
 * Nothing here is specific to a vendor: what is added is exactly what the
 * keychain's trust settings say, decided by `security verify-cert`, so a root
 * an administrator distrusted stays distrusted.
 */
class SystemTrustStore
{
    /**
     * Rebuild at least daily, so a root removed from the keychain stops being trusted.
     */
    public const MAX_AGE_SECONDS = 86400;

    public const DEFAULT_KEYCHAINS = [
        '/Library/Keychains/System.keychain',
    ];

    private const PEM_PATTERN = '/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s';

    /**
     * First line of the bundle: a digest of the trust settings it was built
     * under. OpenSSL ignores text outside PEM blocks.
     */
    private const TRUST_SETTINGS_HEADER = '# acli-trust-settings: ';

    /**
     * @var \Closure(string[]): ?string
     */
    private Closure $runner;

    /**
     * @var \Closure(): int
     */
    private Closure $clock;

    /**
     * @param string[] $keychains
     * @param (\Closure(string[]): ?string)|null $runner
     *   Runs a command and returns its stdout, or null on a non-zero exit.
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        private readonly string $cacheFile,
        private readonly string $systemBundle = '/etc/ssl/cert.pem',
        private readonly array $keychains = self::DEFAULT_KEYCHAINS,
        ?Closure $runner = null,
        ?Closure $clock = null,
    ) {
        $this->runner = $runner ?? static function (array $command): ?string {
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : null;
        };
        $this->clock = $clock ?? time(...);
    }

    /**
     * Applies only where the defect exists, and never over a user's choice.
     *
     * The PHAR runs on the developer's own PHP, whose CA configuration is theirs
     * (Homebrew's, for one, is already built from the keychain). Linux has no
     * keychain; its system store is what OpenSSL reads.
     *
     * @param \Closure(string): (string|false) $getenv
     */
    public static function shouldApply(string $sapi, string $os, Closure $getenv): bool
    {
        return $sapi === 'micro'
            && $os === 'Darwin'
            && !$getenv('SSL_CERT_FILE')
            && !$getenv('SSL_CERT_DIR');
    }

    /**
     * Points OpenSSL at the combined bundle when it applies. Never throws:
     * failing here would turn a possible TLS problem into a certain crash.
     */
    public static function configure(string $cacheFile, string $sapi = PHP_SAPI, string $os = PHP_OS_FAMILY, ?self $store = null): void
    {
        try {
            if (!self::shouldApply($sapi, $os, static fn (string $name): string|false => getenv($name))) {
                return;
            }
            $path = ($store ?? new self($cacheFile))->bundlePath();
            if ($path !== null) {
                putenv('SSL_CERT_FILE=' . $path);
            }
        } catch (Throwable) {
            // Leave OpenSSL on its defaults.
        }
    }

    /**
     * The guidance for a certificate failure, or null when the message is not one.
     */
    public static function tlsHint(string $message): ?string
    {
        if (preg_match('/cURL error (60|77)\b/', $message, $matches) !== 1) {
            return null;
        }
        if ($matches[1] === '77') {
            // The local CA bundle could not be loaded; the server is not involved.
            return 'Acquia CLI could not load its CA certificate bundle. If SSL_CERT_FILE or SSL_CERT_DIR is set, check that it points to a readable PEM file or directory, then try again.';
        }

        return 'Acquia CLI could not verify the server\'s TLS certificate. This usually means a proxy or security tool on your network is inspecting TLS and re-signing certificates with your organization\'s root certificate. Set SSL_CERT_FILE to a PEM bundle that includes that root, then try again.';
    }

    /**
     * Returns the bundle to use, or null when the keychain adds nothing to the
     * system bundle or the keychain cannot be read.
     */
    public function bundlePath(): ?string
    {
        if (!is_file($this->systemBundle)) {
            return null;
        }
        $trustSettings = $this->trustSettingsDigest();
        if ($this->cacheIsFresh($trustSettings)) {
            return $this->cacheFile;
        }

        $fileSystem = new Filesystem();
        $extra = $this->trustedKeychainCas();
        if ($extra === []) {
            // A bundle left from before would go on trusting a root that is gone.
            $fileSystem->remove($this->cacheFile);
            return null;
        }

        $bundle = self::TRUST_SETTINGS_HEADER . $trustSettings . "\n" . file_get_contents($this->systemBundle) . "\n" . implode("\n", $extra) . "\n";
        $fileSystem->dumpFile($this->cacheFile, $bundle);
        $fileSystem->chmod($this->cacheFile, 0600);

        return $this->cacheFile;
    }

    private function cacheIsFresh(string $trustSettings): bool
    {
        if (!is_file($this->cacheFile)) {
            return false;
        }
        // Trust settings live apart from the keychains, so distrusting a root
        // changes no watched mtime; compare what they say instead.
        if (!str_starts_with((string) file_get_contents($this->cacheFile), self::TRUST_SETTINGS_HEADER . $trustSettings)) {
            return false;
        }
        $built = filemtime($this->cacheFile);
        if (($this->clock)() - $built > self::MAX_AGE_SECONDS) {
            return false;
        }
        foreach ([$this->systemBundle, ...$this->keychains] as $source) {
            if (is_file($source) && filemtime($source) > $built) {
                return false;
            }
        }

        return true;
    }

    /**
     * A digest of the user and admin trust settings: what `verify-cert` decides
     * from, besides the certificates themselves. A domain with no settings
     * makes `security` exit non-zero, which counts as empty.
     */
    private function trustSettingsDigest(): string
    {
        $user = ($this->runner)(['/usr/bin/security', 'dump-trust-settings']) ?? '';
        $admin = ($this->runner)(['/usr/bin/security', 'dump-trust-settings', '-d']) ?? '';

        return hash('sha256', $user . "\0" . $admin);
    }

    /**
     * CA certificates in the keychains that macOS trusts for SSL right now.
     *
     * Whether to trust each one is left entirely to `security verify-cert`,
     * which applies the keychain's trust settings, validity dates included.
     *
     * @return string[]
     */
    private function trustedKeychainCas(): array
    {
        $fileSystem = new Filesystem();
        $candidate = $fileSystem->tempnam(sys_get_temp_dir(), 'acli-ca-');
        $trusted = [];

        try {
            foreach ($this->keychains as $keychain) {
                if (!is_file($keychain)) {
                    continue;
                }
                $pems = ($this->runner)(['/usr/bin/security', 'find-certificate', '-a', '-p', $keychain]);
                if ($pems === null) {
                    continue;
                }
                preg_match_all(self::PEM_PATTERN, $pems, $matches);
                foreach ($matches[0] as $pem) {
                    if (!self::isCa($pem)) {
                        continue;
                    }
                    $fileSystem->dumpFile($candidate, $pem);
                    // -L/-l: evaluate it as a root, from local trust settings only;
                    // -R offline: no network. Exits 0 only if macOS trusts it for SSL.
                    if (($this->runner)(['/usr/bin/security', 'verify-cert', '-c', $candidate, '-p', 'ssl', '-L', '-l', '-R', 'offline', '-q']) !== null) {
                        $trusted[] = $pem;
                    }
                }
            }
        } finally {
            $fileSystem->remove($candidate);
        }

        return $trusted;
    }

    /**
     * Only certificates that can issue others belong in a CA bundle; a trusted
     * leaf (a device or MDM identity, say) does not.
     */
    private static function isCa(string $pem): bool
    {
        $cert = openssl_x509_parse($pem);

        return is_array($cert) && str_contains($cert['extensions']['basicConstraints'] ?? '', 'CA:TRUE');
    }
}
