<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Archive;

use Acquia\Cli\Command\Archive\ArchiveExportCommand;
use Acquia\Cli\Tests\Commands\Pull\PullCommandTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @property \Acquia\Cli\Command\Archive\ArchiveExportCommand $command
 */
class ArchiveExporterCommandTest extends PullCommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(ArchiveExportCommand::class);
  }

  public function testArchiveExport(): void {
    touch(Path::join($this->projectDir, '.gitignore'));
    $destinationDir = 'foo';
    $localMachineHelper = $this->mockLocalMachineHelper();
    $fileSystem = $this->mockFileSystem($destinationDir);
    $localMachineHelper->getFilesystem()->willReturn($fileSystem->reveal())->shouldBeCalled();
    $this->mockExecutePvExists($localMachineHelper);
    $this->mockExecuteDrushExists($localMachineHelper);
    $this->mockExecuteDrushStatus($localMachineHelper, TRUE, $this->projectDir);
    $this->mockCreateMySqlDumpOnLocal($localMachineHelper);
    $localMachineHelper->checkRequiredBinariesExist(["tar"])->shouldBeCalled();
    $localMachineHelper->execute(Argument::type('array'), Argument::type('callable'), NULL, TRUE)->willReturn($this->mockProcess())->shouldBeCalled();

    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());

    $this->command->localMachineHelper = $localMachineHelper->reveal();

    $inputs = [
      // ... Do you want to continue? (yes/no) [yes]
      'y',
    ];
    $this->executeCommand([
      'destination-dir' => $destinationDir,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    self::assertStringContainsString('An archive of your Drupal application was created at', $output);
    self::assertStringContainsString('foo/acli-archive-project-', $output);
  }

  protected function mockFileSystem(string $destinationDir): ObjectProphecy {
    $fileSystem = $this->prophet->prophesize(Filesystem::class);
    $fileSystem->mirror($this->projectDir, Argument::type('string'),
      Argument::type(Finder::class), ['override' => TRUE, 'delete' => TRUE],
      Argument::type(Finder::class))->shouldBeCalled();
    $fileSystem->exists($destinationDir)->willReturn(TRUE)->shouldBeCalled();
    $fileSystem->rename(Argument::type('string'), Argument::type('string'))
      ->shouldBeCalled();
    $fileSystem->remove(Argument::type('string'))->shouldBeCalled();
    $fileSystem->mkdir(Argument::type('array'))->shouldBeCalled();
    return $fileSystem;
  }

}
