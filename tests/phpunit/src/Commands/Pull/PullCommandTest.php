<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Pull;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pull\PullCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use GuzzleHttp\Client;
use Prophecy\Argument;

/**
 * @property \Acquia\Cli\Command\Pull\PullCommand $command
 */
class PullCommandTest extends PullCommandTestBase {

  protected function createCommand(): CommandBase {
    $this->httpClientProphecy = $this->prophet->prophesize(Client::class);

    return new PullCommand(
      $this->localMachineHelper,
      $this->datastoreCloud,
      $this->datastoreAcli,
      $this->cloudCredentials,
      $this->telemetryHelper,
      $this->acliRepoRoot,
      $this->clientServiceProphecy->reveal(),
      $this->sshHelper,
      $this->sshDir,
      $this->logger,
      $this->httpClientProphecy->reveal()
    );
  }

  public function testPull(): void {
    // Pull code.
    $environment = $this->mockGetEnvironment();
    $this->createMockGitConfigFile();
    $localMachineHelper = $this->mockLocalMachineHelper();
    $localMachineHelper->checkRequiredBinariesExist(["git"])->shouldBeCalled();
    $finder = $this->mockFinder();
    $localMachineHelper->getFinder()->willReturn($finder->reveal());
    $process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($localMachineHelper, $process, $this->projectDir, $environment->vcs->path);
    $this->mockExecuteGitStatus(FALSE, $localMachineHelper, $this->projectDir);

    // Pull files.
    $sshHelper = $this->mockSshHelper();
    $this->mockGetCloudSites($sshHelper, $environment);
    $this->mockGetFilesystem($localMachineHelper);
    $parts = explode('.', $environment->ssh_url);
    $sitegroup = reset($parts);
    $this->mockExecuteRsync($localMachineHelper, $environment, '/mnt/files/' . $sitegroup . '.' . $environment->name . '/sites/bar/files/', $this->projectDir . '/docroot/sites/bar/files');
    $this->command->sshHelper = $sshHelper->reveal();

    // Pull database.
    $this->mockExecuteMySqlConnect($localMachineHelper, TRUE);
    $this->mockGetBackup($environment);
    $this->mockExecuteMySqlListTables($localMachineHelper, 'drupal');
    $process = $this->mockProcess();
    $localMachineHelper
      ->execute(Argument::type('array'), Argument::type('callable'), NULL, FALSE, NULL, ['MYSQL_PWD' => $this->dbPassword])
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $this->mockExecuteMySqlImport($localMachineHelper, TRUE, TRUE, 'my_db', 'my_dbdev', 'drupal');
    $this->executeCommand([
      '--no-scripts' => TRUE,
    ], [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select a Cloud Platform application:
      self::$INPUT_DEFAULT_CHOICE,
      // Would you like to link the project at ... ?
      'n',
      // Choose an Acquia environment:
      self::$INPUT_DEFAULT_CHOICE,
      self::$INPUT_DEFAULT_CHOICE,
    ]);

    $output = $this->getDisplay();

    $this->assertStringContainsString('Select a Cloud Platform application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose a Cloud Platform environment', $output);
    $this->assertStringContainsString('[0] Dev, dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database [my_db (default)]:', $output);
  }

  public function testMissingLocalRepo(): void {
    $this->setupFsFixture();
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->removeMockGitConfig();
    $this->command = $this->createCommand();
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Execute this command from within a Drupal project directory or an empty directory');
    $inputs = [
      // Would you like to clone a project into the current directory?
      'n',
    ];
    $this->executeCommand([], $inputs);
  }

}
