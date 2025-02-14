<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\NewFromDrupal7Command;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Process\Process;

/**
 * @property \Acquia\Cli\Command\App\NewFromDrupal7Command $command
 */
class NewFromDrupal7CommandTest extends CommandTestBase
{
    protected string $newProjectDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(NewFromDrupal7Command::class);
    }

    /**
     * @return array<mixed>
     */
    public static function provideTestNewFromDrupal7Command(): array
    {
        $repo_root = dirname(__FILE__, 6);
        // Windows accepts paths with either slash (/) or backslash (\), but will
        // not accept a path which contains both a slash and a backslash. Since this
        // may run on any platform, sanitize everything to use slash which is
        // supported on all platforms.
        // @see \Drupal\Core\File\FileSystem::getTempDirectory()
        // @see https://www.php.net/manual/en/function.dirname.php#123472
        $repo_root = str_replace('\\', '/', $repo_root);
        $case_directories = glob($repo_root . '/tests/fixtures/drupal7/*', GLOB_ONLYDIR);
        $cases = [];
        foreach ($case_directories as $case_directory) {
            $cases[basename($case_directory)] = [
                "$case_directory/extensions.json",
                "$repo_root/config/from_d7_recommendations.json",
                "$case_directory/expected.json",
            ];
        }
        return $cases;
    }

    protected function assertValidDateFormat(string $date, string $format): void
    {
        $d = \DateTime::createFromFormat($format, $date);
        $this->assertTrue($d && $d->format($format) == $date, sprintf("Failed asserting that '%s' matches the format '%s'", $date, DATE_ATOM));
    }

    /**
     * Test the app:new:from:drupal7 command.
     *
     * Since this command inspects an actual Drupal site to determine its
     * enabled modules, the inspector must be mocked. A set of Drupal 7
     * extensions is given by the extensions file. This project provides a
     * shell script to help generate that file from an existing Drupal 7 site.
     * An example shell command is given below.
     *
     * @code
     * drush pm:list --pipe --format=json |
     *     /path/to/this/project/tests/fixtures/drupal7/drush_to_extensions_test_file_format.sh
     *     > extensions.json
     * @endcode
     * @param string $extensions_json
     *   An extensions file. See above.
     * @param string $recommendations_json
     *   A recommendations file. The file should have the same format as a file
     *   that would be provided to the --recommendations CLI option.
     * @param string $expected_json
     *   The expected output.
     * @dataProvider provideTestNewFromDrupal7Command
     */
    public function testNewFromDrupal7Command(string $extensions_json, string $recommendations_json, string $expected_json): void
    {
        foreach (func_get_args() as $file) {
            $this->assertFileExists($file, sprintf("The %s test file is missing.", basename($file)));
        }

        $race_condition_proof_tmpdir = sys_get_temp_dir() . '/' . getmypid();
        // The same PHP process may run multiple tests: create the directory
        // only once.
        if (!is_dir($race_condition_proof_tmpdir)) {
            mkdir($race_condition_proof_tmpdir);
        }

        $process = $this->prophet->prophesize(Process::class);
        $process->isSuccessful()->willReturn(true);
        $process->getExitCode()->willReturn(0);

        $localMachineHelper = $this->mockLocalMachineHelper();

        $this->mockGetFilesystem($localMachineHelper);
        $localMachineHelper->checkRequiredBinariesExist(["composer"])
            ->shouldBeCalled();
        $this->mockExecuteComposerCreate($race_condition_proof_tmpdir, $localMachineHelper, $process);
        $localMachineHelper->checkRequiredBinariesExist(["git"])
            ->shouldBeCalled();
        $this->mockExecuteGitInit($localMachineHelper, $race_condition_proof_tmpdir, $process);
        $this->mockExecuteGitAdd($localMachineHelper, $race_condition_proof_tmpdir, $process);
        $this->mockExecuteGitCommit($localMachineHelper, $race_condition_proof_tmpdir, $process);

        $this->executeCommand([
            '--directory' => $race_condition_proof_tmpdir,
            '--recommendations' => $recommendations_json,
            '--stored-analysis' => $extensions_json,
        ]);

        $output = $this->getDisplay();
        $this->assertStringContainsString('Found Drupal 7 site', $output);
        $this->assertStringContainsString('Computing recommendations', $output);
        $this->assertStringContainsString('Great news: found', $output);
        $this->assertStringContainsString('Installing. This may take a few minutes.', $output);
        $this->assertStringContainsString('Drupal project created', $output);
        $this->assertStringContainsString('New 💧 Drupal project created in ' . $race_condition_proof_tmpdir, $output);

        $expected_json = json_decode(file_get_contents($expected_json), true);
        $actual_json = json_decode(file_get_contents($race_condition_proof_tmpdir . '/acli-generated-project-metadata.json'), true);
        // Because the generated datetime will be unique for each test, simply
        // assert that is in the correct format and then set it to the expected
        // value before comparing the actual result with expected result.
        $this->assertValidDateFormat($actual_json['generated'], DATE_ATOM);
        $this->assertValidDateFormat($expected_json['generated'], DATE_ATOM);
        $actual_json['generated'] = $expected_json['generated'];
        $this->assertSame($expected_json, $actual_json);
    }

    protected function mockExecuteComposerCreate(
        string $projectDir,
        ObjectProphecy $localMachineHelper,
        ObjectProphecy $process
    ): void {
        $command = [
            'composer',
            'install',
            '--working-dir',
            $projectDir,
            '--no-interaction',
        ];
        $localMachineHelper
            ->execute($command)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteGitInit(
        ObjectProphecy $localMachineHelper,
        string $projectDir,
        ObjectProphecy $process
    ): void {
        $command = [
            'git',
            'init',
            '--initial-branch=main',
            '--quiet',
        ];
        $localMachineHelper
            ->execute($command, null, $projectDir)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteGitAdd(
        ObjectProphecy $localMachineHelper,
        string $projectDir,
        ObjectProphecy $process
    ): void {
        $command = [
            'git',
            'add',
            '-A',
        ];
        $localMachineHelper
            ->execute($command, null, $projectDir)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }

    protected function mockExecuteGitCommit(
        ObjectProphecy $localMachineHelper,
        string $projectDir,
        ObjectProphecy $process
    ): void {
        $command = [
            'git',
            'commit',
            '--message',
            "Generated by Acquia CLI's app:new:from:drupal7.",
            '--quiet',
        ];
        $localMachineHelper
            ->execute($command, null, $projectDir)
            ->willReturn($process->reveal())
            ->shouldBeCalled();
    }
}
