<?php

namespace Acquia\Cli\Tests\Commands\Archive;

use Acquia\Cli\Command\Archive\ArchiveExportCommand;
use Acquia\Cli\Tests\Commands\Pull\PullCommandTestBase;
use org\bovigo\vfs\vfsStream;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class ArchiveExporterCommandTest.
 *
 * @property \Acquia\Cli\Command\Archive\ArchiveExportCommand $command
 */
class ArchiveExporterCommandTest extends PullCommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ArchiveExportCommand::class);
  }

  /**
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  public function testArchiveExport(): void {
    vfsStream::newFile('.gitignore')->at($this->vfsProject);
    $destination_dir = sys_get_temp_dir();
    $local_machine_helper = $this->mockLocalMachineHelper();
    $file_system = $this->mockFileSystem($destination_dir);
    $local_machine_helper->getFilesystem()->willReturn($file_system->reveal())->shouldBeCalled();
    $this->mockExecutePvExists($local_machine_helper);
    $this->mockExecuteDrushExists($local_machine_helper);
    $this->mockExecuteDrushStatus($local_machine_helper, TRUE, $this->projectDir);
    $this->mockCreateMySqlDumpOnLocal($local_machine_helper);
    $local_machine_helper->checkRequiredBinariesExist(["tar"])->shouldBeCalled();
    $local_machine_helper->execute(Argument::type('array'), Argument::type('callable'), NULL, TRUE)->willReturn($this->mockProcess())->shouldBeCalled();

    $finder = $this->mockFinder();
    $local_machine_helper->getFinder()->willReturn($finder->reveal());

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $inputs = [
      // ... Do you want to continue? (yes/no) [yes]
      'y',
    ];
    $this->executeCommand([
      'destination-dir' => $destination_dir,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    self::assertStringContainsString('An archive of your Drupal application was created at', $output);
  }

  /**
   * @param string $destination_dir
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockFileSystem(string $destination_dir): ObjectProphecy {
    $file_system = $this->prophet->prophesize(Filesystem::class);
    $file_system->mirror($this->projectDir, Argument::type('string'),
      Argument::type(Finder::class), ['override' => TRUE, 'delete' => TRUE],
      Argument::type(Finder::class))->shouldBeCalled();
    $file_system->exists($destination_dir)->willReturn(TRUE)->shouldBeCalled();
    $file_system->rename(Argument::type('string'), Argument::type('string'))
      ->shouldBeCalled();
    $file_system->remove(Argument::type('string'))->shouldBeCalled();
    $file_system->mkdir(Argument::type('array'))->shouldBeCalled();
    return $file_system;
  }

}
