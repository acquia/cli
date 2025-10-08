<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @property SshKeyCreateCommand $command
 */
class SshKeyCreateCommandTest extends CommandTestBase
{
    protected static string $filename = 'id_rsa_acli_test';

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyCreateCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public static function providerTestCreate(): array
    {
        return [
            [
                true,
                // Args.
                [
                    '--filename' => self::$filename,
                    '--password' => 'acli123',
                ],
                // Inputs.
                [],
            ],
            [
                true,
                // Args.
                [],
                // Inputs.
                [
                    // Enter a filename for your new local SSH key:
                    self::$filename,
                    // Enter a password for your SSH key:
                    'acli123',
                ],
            ],
            [
                false,
                // Args.
                [],
                // Inputs.
                [
                    // Enter a filename for your new local SSH key:
                    self::$filename,
                    // Enter a password for your SSH key:
                    'acli123',
                ],
            ],
            [
                true,
                // Args.
                [
                    '--filename' => self::$filename,
                    '--password' => 'two words',
                ],
                // Inputs.
                [],
            ],
            [
                true,
                // Args.
                [],
                // Inputs.
                [
                    // Enter a filename for your new local SSH key:
                    self::$filename,
                    // Enter a password for your SSH key:
                    'password with spaces',
                ],
            ],
            [
                true,
                // Args.
                [
                    '--filename' => self::$filename,
                    '--password' => 'password with "quotes"',
                ],
                // Inputs.
                [],
            ],
        ];
    }

    /**
     * @dataProvider providerTestCreate
     * @group brokenProphecy
     */
    public function testCreate(mixed $sshAddSuccess, mixed $args, mixed $inputs): void
    {
        $sshKeyFilepath = Path::join($this->sshDir, '/' . self::$filename);
        $this->fs->remove($sshKeyFilepath);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath('~/.passphrase')
            ->willReturn('~/.passphrase');
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $this->mockAddSshKeyToAgent($localMachineHelper, $fileSystem);
        $this->mockSshAgentList($localMachineHelper, $sshAddSuccess);
        $this->mockGenerateSshKey($localMachineHelper);

        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        $this->executeCommand($args, $inputs);
    }

    /**
     * Test that passwords with spaces are properly escaped in shell commands.
     *
     * @group brokenProphecy
     */
    public function testCreateWithPasswordContainingSpaces(): void
    {
        $sshKeyFilepath = Path::join($this->sshDir, '/' . self::$filename);
        $this->fs->remove($sshKeyFilepath);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath('~/.passphrase')
            ->willReturn('~/.passphrase');
        $fileSystem = $this->prophet->prophesize(Filesystem::class);

        // Mock the SSH key generation.
        $this->mockGenerateSshKey($localMachineHelper);

        // Mock SSH agent list to return false so addSshKeyToAgent is called.
        $this->mockSshAgentList($localMachineHelper, false);

        // Mock the addSshKeyToAgent method with specific command verification.
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getOutput()->willReturn('');
        $process->getErrorOutput()->willReturn('');

        // Verify the exact command structure to catch string concatenation bugs.
        $localMachineHelper->executeFromCmd(
            Argument::that(function ($command) {
                // Verify the command has the correct structure with properly escaped password.
                $escapedPassword = escapeshellarg('two words');
                $expectedPattern = '/^SSH_PASS=' . preg_quote($escapedPassword, '/') . ' DISPLAY=1 SSH_ASKPASS=.* ssh-add .*$/';
                return preg_match($expectedPattern, $command) === 1;
            }),
            null,
            null,
            false
        )->willReturn($process->reveal())->shouldBeCalled();

        $fileSystem->tempnam(Argument::type('string'), 'acli')
            ->willReturn('something');
        $fileSystem->chmod('something', 493)->shouldBeCalled();
        $fileSystem->remove('something')->shouldBeCalled();
        $localMachineHelper->writeFile('something', Argument::type('string'))
            ->shouldBeCalled();

        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        // Execute command with password containing spaces
        // This should not throw an exception due to proper password escaping.
        $this->executeCommand([
            '--filename' => self::$filename,
            '--password' => 'two words',
        ], []);
    }

    /**
     * Test password escaping functionality directly.
     */
    public function testPasswordEscaping(): void
    {
        // Test various password formats to ensure they are properly escaped.
        $testPasswords = [
            'simple',
            'two words',
            'password with "quotes"',
            'password with \'single quotes\'',
            'password with $special chars',
            'password with spaces and "mixed" quotes',
        ];

        foreach ($testPasswords as $password) {
            $escaped = escapeshellarg($password);

            // Verify that the escaped password is properly quoted.
            // On Windows, escapeshellarg() uses double quotes; on Unix, it uses single quotes.
            $isWindows = PHP_OS_FAMILY === 'Windows';
            $expectedQuote = $isWindows ? '"' : "'";

            $this->assertStringStartsWith($expectedQuote, $escaped, "Password '$password' should start with $expectedQuote quote");
            $this->assertStringEndsWith($expectedQuote, $escaped, "Password '$password' should end with $expectedQuote quote");

            // For passwords with quotes, the escaping changes the content
            // so we need to check differently.
            if (str_contains($password, "'") || str_contains($password, '"')) {
                // The escaped version should contain the password content but with escaped quotes.
                $this->assertStringContainsString("password with", $escaped, "Escaped password should contain password content");
            } else {
                // Verify that the original password is contained within the escaped version.
                $this->assertStringContainsString($password, $escaped, "Escaped password should contain original password");
            }
        }

        // Test that malicious passwords are safely escaped.
        $maliciousPassword = '; rm -rf /';
        $safeEscaped = escapeshellarg($maliciousPassword);
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $expectedEscaped = $isWindows ? '"; rm -rf /"' : "'; rm -rf /'";
        $this->assertEquals($expectedEscaped, $safeEscaped, "Malicious password should be safely escaped");
    }

    /**
     * Test command construction with various password formats to catch string concatenation bugs.
     *
     * @group brokenProphecy
     */
    public function testCommandConstructionWithVariousPasswords(): void
    {
        $testPasswords = [
            'simple',
            'two words',
            'password with "quotes"',
            'password with \'single quotes\'',
            'password with $special chars',
        ];

        foreach ($testPasswords as $password) {
            $sshKeyFilepath = Path::join($this->sshDir, '/' . self::$filename . '_' . md5($password));
            $this->fs->remove($sshKeyFilepath);
            $localMachineHelper = $this->mockLocalMachineHelper();
            $localMachineHelper->getLocalFilepath('~/.passphrase')
                ->willReturn('~/.passphrase');
            $fileSystem = $this->prophet->prophesize(Filesystem::class);

            // Mock the SSH key generation.
            $this->mockGenerateSshKey($localMachineHelper);

            // Mock SSH agent list to return false so addSshKeyToAgent is called.
            $this->mockSshAgentList($localMachineHelper, false);

            // Mock the addSshKeyToAgent method with specific command verification.
            $process = $this->prophet->prophesize(Process::class);
            $process->isSuccessful()->willReturn(true);

            // Verify the command contains the properly escaped password and correct structure.
            $localMachineHelper->executeFromCmd(
                Argument::that(function ($command) use ($password) {
                    $escapedPassword = escapeshellarg($password);
                    // Verify the command has the exact expected structure.
                    $expectedPattern = '/^SSH_PASS=' . preg_quote($escapedPassword, '/') . ' DISPLAY=1 SSH_ASKPASS=.* ssh-add .*$/';
                    return preg_match($expectedPattern, $command) === 1;
                }),
                null,
                null,
                false
            )->willReturn($process->reveal())->shouldBeCalled();

            $fileSystem->tempnam(Argument::type('string'), 'acli')
                ->willReturn('something');
            $fileSystem->chmod('something', 493)->shouldBeCalled();
            $fileSystem->remove('something')->shouldBeCalled();
            $localMachineHelper->writeFile('something', Argument::type('string'))
                ->shouldBeCalled();

            $localMachineHelper->getFilesystem()
                ->willReturn($fileSystem->reveal())
                ->shouldBeCalled();

            // Execute command with the test password.
            $this->executeCommand([
                '--filename' => self::$filename . '_' . md5($password),
                '--password' => $password,
            ], []);
        }
    }

    /**
     * Test exact command structure to catch string concatenation bugs.
     *
     * @group brokenProphecy
     */
    public function testExactCommandStructure(): void
    {
        $password = 'test password';
        $sshKeyFilepath = Path::join($this->sshDir, '/' . self::$filename);
        $this->fs->remove($sshKeyFilepath);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath('~/.passphrase')
            ->willReturn('~/.passphrase');
        $fileSystem = $this->prophet->prophesize(Filesystem::class);

        // Mock the SSH key generation.
        $this->mockGenerateSshKey($localMachineHelper);

        // Mock SSH agent list to return false so addSshKeyToAgent is called.
        $this->mockSshAgentList($localMachineHelper, false);

        // Mock the addSshKeyToAgent method with very specific command verification.
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);

        // Verify the exact command structure with all components in correct order.
        $localMachineHelper->executeFromCmd(
            Argument::that(function ($command) use ($password) {
                $escapedPassword = escapeshellarg($password);

                // Check that all required components are present in the correct order.
                $components = [
                    "SSH_PASS=$escapedPassword",
                    'DISPLAY=1',
                    'SSH_ASKPASS=',
                    'ssh-add',
                ];

                $position = 0;
                foreach ($components as $component) {
                    $foundPos = strpos($command, $component, $position);
                    if ($foundPos === false) {
                        return false;
                    }
                    $position = $foundPos + strlen($component);
                }

                // Additional check: ensure the command starts correctly.
                return str_starts_with($command, "SSH_PASS=$escapedPassword DISPLAY=1 SSH_ASKPASS=");
            }),
            null,
            null,
            false
        )->willReturn($process->reveal())->shouldBeCalled();

        $fileSystem->tempnam(Argument::type('string'), 'acli')
            ->willReturn('something');
        $fileSystem->chmod('something', 493)->shouldBeCalled();
        $fileSystem->remove('something')->shouldBeCalled();
        $localMachineHelper->writeFile('something', Argument::type('string'))
            ->shouldBeCalled();

        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        // Execute command.
        $this->executeCommand([
            '--filename' => self::$filename,
            '--password' => $password,
        ], []);
    }

    /**
     * Test that malformed commands would cause failures.
     * This helps catch string concatenation bugs that create invalid commands.
     *
     * @group brokenProphecy
     */
    public function testMalformedCommandDetection(): void
    {
        $password = 'test password';
        $sshKeyFilepath = Path::join($this->sshDir, '/' . self::$filename);
        $this->fs->remove($sshKeyFilepath);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath('~/.passphrase')
            ->willReturn('~/.passphrase');
        $fileSystem = $this->prophet->prophesize(Filesystem::class);

        // Mock the SSH key generation.
        $this->mockGenerateSshKey($localMachineHelper);

        // Mock SSH agent list to return false so addSshKeyToAgent is called.
        $this->mockSshAgentList($localMachineHelper, false);

        // Mock the addSshKeyToAgent method with strict command validation.
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);

        // Verify the command has the exact expected format and reject malformed versions.
        $localMachineHelper->executeFromCmd(
            Argument::that(function ($command) use ($password) {
                $escapedPassword = escapeshellarg($password);

                // Reject commands that are missing required spaces or have wrong order.
                $malformedPatterns = [
                    // Missing space.
                    '/SSH_PASS=' . preg_quote($escapedPassword, '/') . 'DISPLAY=1/',
                    // Missing DISPLAY=1.
                    '/SSH_PASS=' . preg_quote($escapedPassword, '/') . 'SSH_ASKPASS=/',
                    // Missing space.
                    '/DISPLAY=1SSH_ASKPASS=/',
                    // Missing space.
                    '/SSH_ASKPASS=ssh-add/',
                    // Missing private key filepath.
                    '/ssh-add$/',
                ];

                foreach ($malformedPatterns as $pattern) {
                    if (preg_match($pattern, $command)) {
                        return false;
                    }
                }

                // Accept only the correctly formatted command.
                $correctPattern = '/^SSH_PASS=' . preg_quote($escapedPassword, '/') . ' DISPLAY=1 SSH_ASKPASS=.* ssh-add .*$/';
                return preg_match($correctPattern, $command) === 1;
            }),
            null,
            null,
            false
        )->willReturn($process->reveal())->shouldBeCalled();

        $fileSystem->tempnam(Argument::type('string'), 'acli')
            ->willReturn('something');
        $fileSystem->chmod('something', 493)->shouldBeCalled();
        $fileSystem->remove('something')->shouldBeCalled();
        $localMachineHelper->writeFile('something', Argument::type('string'))
            ->shouldBeCalled();

        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        // Execute command.
        $this->executeCommand([
            '--filename' => self::$filename,
            '--password' => $password,
        ], []);
    }

    /**
     * Test that catches specific concatenation mutants.
     */
    public function testCommandConcatenationMutants(): void
    {
        $sshKeyFilepath = Path::join($this->sshDir, '/' . self::$filename);
        $this->fs->remove($sshKeyFilepath);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $localMachineHelper->getLocalFilepath('~/.passphrase')
            ->willReturn('~/.passphrase');
        $fileSystem = $this->prophet->prophesize(Filesystem::class);

        // Mock the SSH key generation.
        $this->mockGenerateSshKey($localMachineHelper);

        // Mock SSH agent list to return false so addSshKeyToAgent is called.
        $this->mockSshAgentList($localMachineHelper, false);

        // Mock the addSshKeyToAgent method with specific command verification.
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);

        // Mock the executeFromCmd call with specific checks for concatenation mutants.
        $localMachineHelper->executeFromCmd(
            Argument::that(function ($command) {
                $escapedPassword = escapeshellarg('test password');

                // Check for the specific escaped mutants:
                // 1. ConcatOperandRemoval: SSH_ASKPASS= removed.
                if (!str_contains($command, 'SSH_ASKPASS=')) {
                    return false;
                }

                // 2. Concat: ssh-add and privateKeyFilepath concatenated without space
                if (str_contains($command, 'ssh-add/') || str_contains($command, 'ssh-add.')) {
                    return false;
                }

                // 3. Concat: ssh-add moved to end without space
                if (str_ends_with($command, 'ssh-add')) {
                    return false;
                }

                // 4. ConcatOperandRemoval: privateKeyFilepath removed
                if (!preg_match('/ssh-add\s+\S+$/', $command)) {
                    return false;
                }

                // Verify the complete command structure.
                $expectedPattern = '/^SSH_PASS=' . preg_quote($escapedPassword, '/') . '\s+DISPLAY=1\s+SSH_ASKPASS=\S+\s+ssh-add\s+\S+$/';
                return preg_match($expectedPattern, $command) === 1;
            }),
            null,
            null,
            false
        )->willReturn($process->reveal())->shouldBeCalled();

        $fileSystem->tempnam(Argument::type('string'), 'acli')
            ->willReturn('something');
        $fileSystem->chmod('something', 493)->shouldBeCalled();
        $fileSystem->remove('something')->shouldBeCalled();
        $localMachineHelper->writeFile('something', Argument::type('string'))
            ->shouldBeCalled();

        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem->reveal())
            ->shouldBeCalled();

        // Execute command with password containing spaces.
        $this->executeCommand([
            '--filename' => self::$filename,
            '--password' => 'test password',
        ], []);
    }
}
