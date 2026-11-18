<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\SshHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophet;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

#[CoversClass(SshHelper::class)]
class SshHelperTest extends TestCase
{
    private const SSH_URL = 'site.dev@sitedev.ssh.hosted.acquia-sites.com';

    private Prophet $prophet;

    protected function setUp(): void
    {
        $this->prophet = new Prophet();
    }

    protected function tearDown(): void
    {
        $this->prophet->checkPredictions();
    }

    /**
     * @return array{SshHelper, \stdClass, BufferedOutput}
     */
    private function sshHelper(bool $useTty = false): array
    {
        // A holder object, because a reference inside a returned array literal
        // is not preserved for the caller.
        $capture = new \stdClass();
        $capture->command = [];
        $process = $this->prophet->prophesize(Process::class);
        $process->getExitCode()->willReturn(0);
        $process->isSuccessful()->willReturn(true);

        $localMachineHelper = $this->prophet->prophesize(LocalMachineHelper::class);
        $localMachineHelper->checkRequiredBinariesExist(['ssh'])->shouldBeCalled();
        $localMachineHelper->useTty()->willReturn($useTty);
        $localMachineHelper
            ->execute(Argument::type('array'), Argument::cetera())
            ->will(function (array $args) use ($capture, $process) {
                $capture->command = $args[0];
                $capture->args = $args;
                return $process->reveal();
            });

        $output = new BufferedOutput();
        $logger = $this->prophet->prophesize(LoggerInterface::class);

        $helper = new SshHelper($output, $localMachineHelper->reveal(), $logger->reveal());

        return [$helper, $capture, $output];
    }

    public function testExecuteCommandAllocatesTty(): void
    {
        [$helper, $capture] = $this->sshHelper();
        $helper->executeCommand(self::SSH_URL, ['ls']);

        $this->assertContains('-t', $capture->command, 'Interactive remote commands must keep the pty.');
    }

    /**
     * A pty echoes its input and translates line endings, which corrupts a
     * streamed SQL payload, and Symfony Process cannot combine TTY mode with
     * piped input at all.
     */
    public function testExecuteCommandWithoutTtyOmitsTty(): void
    {
        [$helper, $capture] = $this->sshHelper();
        $helper->executeCommandWithoutTty(self::SSH_URL, ['ls']);

        $this->assertNotContains('-t', $capture->command, 'The streamed path must not allocate a pty.');
    }

    /**
     * Guards against the non-TTY argument list being written by hand and
     * quietly dropping host-key verification. CLAUDE.md forbids weakening
     * StrictHostKeyChecking, and no functional test would catch it.
     */
    public function testNonTtyPathRetainsConnectionHardening(): void
    {
        [$helper, $capture] = $this->sshHelper();
        $helper->executeCommandWithoutTty(self::SSH_URL, ['ls']);

        $this->assertContains('-o StrictHostKeyChecking=accept-new', $capture->command);
        $this->assertContains('-o AddressFamily inet', $capture->command);
        $this->assertContains('-o LogLevel=ERROR', $capture->command);
    }

    /**
     * The two argument lists must differ by exactly the pty flag, so the
     * non-TTY path cannot drift away from the standard one.
     */
    public function testNonTtyPathDiffersOnlyByTtyFlag(): void
    {
        [$helperA, $captureA] = $this->sshHelper();
        $helperA->executeCommand(self::SSH_URL, ['ls']);

        [$helperB, $captureB] = $this->sshHelper();
        $helperB->executeCommandWithoutTty(self::SSH_URL, ['ls']);

        $this->assertSame(
            array_values(array_diff($captureA->command, $captureB->command)),
            ['-t'],
            'The non-TTY argument list must differ from the standard one only by -t.'
        );
    }

    /**
     * Removing -t from the ssh argv is only half the job. LocalMachineHelper
     * calls setTty(useTty()) whenever $printOutput is true, and Symfony's
     * UnixPipes::getDescriptors() hands a TTY-mode process ['file','/dev/tty','r']
     * for stdin — the configured input is then discarded with no error at all.
     * A streamed dump would vanish and the import would exit 0 having written
     * nothing, so the non-TTY path must never let TTY mode be set.
     */
    public function testNonTtyPathDisablesLocalTtyMode(): void
    {
        [$helper, $capture] = $this->sshHelper(true);
        $helper->executeCommandWithoutTty(self::SSH_URL, ['ls']);

        $this->assertFalse($capture->args[3], 'printOutput must be false so LocalMachineHelper never calls setTty().');
    }

    public function testTtyPathStillEnablesLocalTtyMode(): void
    {
        [$helper, $capture] = $this->sshHelper(true);
        $helper->executeCommand(self::SSH_URL, ['ls']);

        $this->assertTrue($capture->args[3], 'Interactive commands must keep their existing TTY behaviour.');
    }

    /**
     * configureProcess() calls setInput(STDIN) whenever stdin is not a tty,
     * which would bind acli's own standard input to the ssh process and
     * override whatever the caller means to stream.
     */
    public function testNonTtyPathDoesNotInheritAcliStdin(): void
    {
        [$helper, $capture] = $this->sshHelper();
        $helper->executeCommandWithoutTty(self::SSH_URL, ['ls']);

        $this->assertFalse($capture->args[6], 'The streamed path must not inherit acli STDIN.');
    }

    /**
     * getOutputCallback() returns a no-op when useTty() is true, on the
     * assumption that the pty writes straight to the terminal. With the pty
     * gone, output arrives through pipes instead, so that assumption would
     * silently swallow every line the remote command produces — including the
     * policy-refusal message.
     */
    public function testNonTtyPathWritesOutputWhenTerminalIsInteractive(): void
    {
        [$helper, $capture, $output] = $this->sshHelper(true);
        $helper->executeCommandWithoutTty(self::SSH_URL, ['ls']);

        ($capture->args[1])(Process::OUT, 'remote output');

        $this->assertSame('remote output', $output->fetch(), 'Remote output must still reach the user without a pty.');
    }

    /**
     * The streamed import pipes mysqldump straight into the remote client, so
     * the producer process has to reach LocalMachineHelper as explicit input
     * rather than being replaced by acli's own STDIN.
     */
    public function testExecuteCommandWithoutTtyForwardsStreamedInput(): void
    {
        [$helper, $capture] = $this->sshHelper();
        $producer = new Process(['printf', 'INSERT INTO node VALUES (1);']);

        $helper->executeCommandWithoutTty(self::SSH_URL, ['ls'], true, null, null, $producer);

        $this->assertSame($producer, $capture->args[7], 'The producer must be forwarded as explicit input.');
        $this->assertFalse($capture->args[6], 'acli STDIN must still be suppressed.');
    }

    /**
     * The TTY path must keep its original six-argument call so the existing
     * command tests remain valid regression guards.
     */
    public function testTtyPathCallShapeIsUnchanged(): void
    {
        [$helper, $capture] = $this->sshHelper();
        $helper->executeCommand(self::SSH_URL, ['ls']);

        $this->assertCount(6, $capture->args, 'The interactive path must still pass exactly six arguments.');
    }

    /**
     * The transport probe expects to be refused on a restricted environment —
     * that refusal is the answer, not an error — so a caller that passes
     * $printOutput = false must not have the policy banner written for it.
     */
    public function testNonTtyPathSuppressesOutputWhenPrintOutputIsFalse(): void
    {
        [$helper, $capture, $output] = $this->sshHelper(true);
        $helper->executeCommandWithoutTty(self::SSH_URL, ['bash', '-c', 'true'], false);

        ($capture->args[1])(Process::ERR, 'Trial subscriptions allow only Drupal (Drush) commands.');

        $this->assertSame('', $output->fetch(), 'A caller that asked not to print must not surface remote output.');
    }

    /**
     * The interactive path's output handling is untouched: it has never
     * consulted $printOutput and must keep not consulting it.
     */
    public function testTtyPathOutputHandlingIsUnchangedByPrintOutput(): void
    {
        [$helper, $capture, $output] = $this->sshHelper(true);
        $helper->executeCommand(self::SSH_URL, ['ls'], false);

        ($capture->args[1])(Process::OUT, 'interactive output');

        $this->assertSame('', $output->fetch(), 'With a local tty the pty writes directly; the callback stays a no-op.');
    }
}
