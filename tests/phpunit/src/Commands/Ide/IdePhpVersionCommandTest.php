<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @property \Acquia\Cli\Command\Ide\IdePhpVersionCommand $command
 */
class IdePhpVersionCommandTest extends CommandTestBase
{
    use IdeRequiredTestTrait;

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(IdePhpVersionCommand::class);
    }

    /**
     * @return array<mixed>
     */
    public function providerTestIdePhpVersionCommand(): array
    {
        return [
            ['7.4'],
            ['8.0'],
            ['8.1'],
        ];
    }

    /**
     * @dataProvider providerTestIdePhpVersionCommand
     */
    public function testIdePhpVersionCommand(string $version): void
    {
        $localMachineHelper = $this->mockLocalMachineHelper();
        $this->mockRestartPhp($localMachineHelper);
        $mockFileSystem = $this->mockGetFilesystem($localMachineHelper);
        $phpFilepathPrefix = $this->fs->tempnam(sys_get_temp_dir(), 'acli_php_stub_');
        $phpStubFilepath = $phpFilepathPrefix . $version;
        $mockFileSystem->exists($phpStubFilepath)->willReturn(true);
        $phpVersionFilePath = $this->fs->tempnam(sys_get_temp_dir(), 'acli_php_version_file_');
        $mockFileSystem->dumpFile($phpVersionFilePath, $version)
            ->shouldBeCalled();

        $this->command->setPhpVersionFilePath($phpVersionFilePath);
        $this->command->setIdePhpFilePathPrefix($phpFilepathPrefix);
        $this->executeCommand([
            'version' => $version,
        ], []);
    }

    /**
     * @return array<mixed>
     */
    public function providerTestIdePhpVersionCommandFailure(): array
    {
        return [
            ['6.3', AcquiaCliException::class],
            ['6', ValidatorException::class],
            ['7', ValidatorException::class],
            ['7.', ValidatorException::class],
        ];
    }

    /**
     * @dataProvider providerTestIdePhpVersionCommandFailure
     */
    public function testIdePhpVersionCommandFailure(string $version, string $exceptionClass): void
    {
        $this->expectException($exceptionClass);
        $this->executeCommand([
            'version' => $version,
        ]);
    }

    public function testIdePhpVersionCommandOutsideIde(): void
    {
        IdeHelper::unsetCloudIdeEnvVars();
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('This command can only be run inside of an Acquia Cloud IDE');
        $this->executeCommand([
            'version' => '7.3',
        ]);
    }

    protected function mockRestartPhp(ObjectProphecy|LocalMachineHelper $localMachineHelper): ObjectProphecy
    {
        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);
        $localMachineHelper->execute([
            'supervisorctl',
            'restart',
            'php-fpm',
        ], null, null, false)->willReturn($process->reveal())->shouldBeCalled();
        return $process;
    }

    /**
     * @return \Prophecy\Prophecy\ObjectProphecy|\Symfony\Component\Filesystem\Filesystem
     */
    protected function mockGetFilesystem(ObjectProphecy|LocalMachineHelper $localMachineHelper): ObjectProphecy|Filesystem
    {
        $fileSystem = $this->prophet->prophesize(Filesystem::class);
        $localMachineHelper->getFilesystem()
            ->willReturn($fileSystem)
            ->shouldBeCalled();

        return $fileSystem;
    }
}
