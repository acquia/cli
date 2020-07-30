<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Ide\IdePhpVersionCommand;
use Acquia\Cli\Command\RefreshCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestBase;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class RefreshCommandTest.
 *
 * @property \Acquia\Cli\Command\RefreshCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class RefreshCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(RefreshCommand::class);
  }

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->removeMockGitConfig();
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->removeMockGitConfig();
  }

  public function testRefreshCode(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();

    $local_machine_helper = $this->mockLocalMachineHelper();
    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $dirty_process = $this->mockProcess();
    $this->mockExecuteGitFetchAndCheckout($local_machine_helper, $dirty_process, $environments_response);
    $this->mockExecuteGitStatus(FALSE, $dirty_process, $local_machine_helper);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select an Acquia Cloud application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--no-files' => TRUE,
      '--no-databases' => TRUE,
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose an Acquia Cloud environment to copy from:', $output);
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
  }

  public function testRefreshDatabases(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockAcsfEnvironmentsRequest($applications_response);
    $this->createMockGitConfigFile();
    $this->mockDatabasesResponse($environments_response);
    $acsf_multisite_fetch_process = $this->mockProcess();
    $acsf_multisite_fetch_process->getOutput()
      ->willReturn(
        file_get_contents(
          Path::join($this->fixtureDir, '/multisite-config.json')
        ))
      ->shouldBeCalled();
    $ssh_helper = $this->prophet->prophesize(SshHelper::class);
    $ssh_helper->executeCommand(Argument::type('object'), ['cat', '/var/www/site-php/site.dev/multisite-config.json'])
      ->willReturn($acsf_multisite_fetch_process->reveal())
      ->shouldBeCalled();
    $process = $this->mockProcess();

    $local_machine_helper = $this->mockLocalMachineHelper();
    // Set up file system.
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();

    // Database.
    $mysql_dump_successful = TRUE;
    $this->mockExecuteSshMySqlDump($ssh_helper, $environments_response, $mysql_dump_successful);
    $this->mockWriteMySqlDump($local_machine_helper);
    $this->mockExecuteMySqlDropDb($local_machine_helper, $process);
    $this->mockExecuteMySqlCreateDb($local_machine_helper, $process);
    $this->mockExecutePvExists($local_machine_helper);
    $this->mockExecuteMySqlImport($local_machine_helper, $process);

    $this->command->localMachineHelper = $local_machine_helper->reveal();
    $this->command->sshHelper = $ssh_helper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select an Acquia Cloud application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--no-files' => TRUE,
      '--no-code' => TRUE,
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose an Acquia Cloud environment to copy from:', $output);
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database to copy:', $output);
    $this->assertStringContainsString('jxr5000596dev (oracletest1.dev-profserv2.acsitefactory.com)', $output);
    $this->assertStringContainsString('profserv2 (default)', $output);
  }

  public function testRefreshFiles(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $this->mockExecuteRsync($local_machine_helper, $environments_response, $process);

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select an Acquia Cloud application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--no-databases' => TRUE,
      '--no-code' => TRUE,
      '--no-scripts' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose an Acquia Cloud environment to copy from:', $output);
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
  }

  public function testRefreshScripts(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    // Composer.
    $this->mockExecuteComposerExists($local_machine_helper);
    $this->mockExecuteComposerInstall($local_machine_helper, $process);

    // Drush.
    $drush_connection_exists = TRUE;
    $this->mockExecuteDrushExists($local_machine_helper);
    $this->mockExecuteDrushStatus($local_machine_helper, $drush_connection_exists);
    if ($drush_connection_exists) {
      $this->mockExecuteDrushCacheRebuild($local_machine_helper, $process);
      $this->mockExecuteDrushSqlSanitize($local_machine_helper, $process);
    }

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select an Acquia Cloud application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];

    $this->executeCommand([
      '--no-databases' => TRUE,
      '--no-code' => TRUE,
      '--no-files' => TRUE,
    ], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose an Acquia Cloud environment to copy from:', $output);
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
  }

  public function testMissingLocalRepo(): void {
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->command = $this->createCommand();
    try {
      $inputs = [
        // Would you like to clone a project into the current directory?
        'n',
      ];
      $this->executeCommand([], $inputs);
    }
    catch (AcquiaCliException $e) {
      $this->assertEquals('Please execute this command from within a Drupal project directory or an empty directory', $e->getMessage());
    }
  }

  public function testCloneRepo(): void {
    // Unset repo root. Mimics failing to find local git repo. Command must be re-created
    // to re-inject the parameter into the command.
    $this->acliRepoRoot = '';
    $this->command = $this->createCommand();
    // Client responses.
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environments_response = $this->mockEnvironmentsRequest($applications_response);
    $local_machine_helper = $this->mockLocalMachineHelper();
    $process = $this->mockProcess();
    $dir = Path::join($this->fixtureDir, 'empty-dir');
    $this->fs->mkdir([$dir]);
    $this->mockExecuteGitClone($local_machine_helper, $environments_response, $process, $dir);
    $local_machine_helper->getFinder()->willReturn(new Finder());

    $this->command->localMachineHelper = $local_machine_helper->reveal();

    $inputs = [
      // Would you like to clone a project into the current directory?
      'y',
      // Please select an Acquia Cloud application:
      0,
      // Would you like to link the project at ... ?
      'n',
      // Please choose an Acquia environment:
      0,
    ];
    $this->executeCommand([
      '--no-databases' => TRUE,
      '--no-files' => TRUE,
      '--no-scripts' => TRUE,
      'dir' => $dir,
    ], $inputs);
    $this->prophet->checkPredictions();
  }

  public function testMatchPhpVersion(): void {
    IdeRequiredTestBase::setCloudIdeEnvVars();
    $this->application->addCommands([
      $this->injectCommand(IdePhpVersionCommand::class),
    ]);
    $this->command = $this->createCommand();
    $environment_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $environment_response->configuration->php->version = '7.1';
    $environment_response->sshUrl = $environment_response->ssh_url;
    $this->clientProphecy->request('get',
      "/environments/" . $environment_response->id)
      ->willReturn($environment_response)
      ->shouldBeCalled();

    $this->executeCommand([
      '--no-code' => TRUE,
      '--no-databases' => TRUE,
      '--no-files' => TRUE,
      '--no-scripts' => TRUE,
      '--cloud-env-uuid' => $environment_response->id,
      'dir' => '/home/ide/project',
    ], [
      // Please choose an Acquia environment:
      0,
      // Would you like to change the PHP version on this IDE to match the PHP version on ... ?
      'n',
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString("Would you like to change the PHP version on this IDE to match the PHP version on {$environment_response->name} ({$environment_response->configuration->php->version})?", $output);
  }

  /**
   * @param object $environments_response
   *
   * @return object
   */
  protected function mockDatabasesResponse(
    $environments_response
  ) {
    $databases_response = json_decode(file_get_contents(Path::join($this->fixtureDir, '/acsf_db_response.json')));
    $this->clientProphecy->request('get',
      "/environments/{$environments_response->id}/databases")
      ->willReturn($databases_response)
      ->shouldBeCalled();

    return $databases_response;
  }

  /**
   * @param bool $success
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockProcess($success = TRUE): ObjectProphecy {
    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    return $process;
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param $has_connection
   */
  protected function mockExecuteDrushStatus(
    ObjectProphecy $local_machine_helper,
    $has_connection
  ): void {
    $drush_status_process = $this->prophet->prophesize(Process::class);
    $drush_status_process->isSuccessful()->willReturn($has_connection);
    $drush_status_process->getExitCode()->willReturn($has_connection ? 0 : 1);
    $drush_status_process->getOutput()
      ->willReturn(json_encode(['db-status' => 'Connected']));
    $local_machine_helper
      ->execute([
        'drush',
        'status',
        '--fields=db-status,drush-version',
        '--format=json',
        '--no-interaction',
      ], Argument::type('callable'), $this->projectFixtureDir, FALSE)
      ->willReturn($drush_status_process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteDrushCacheRebuild(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
      ->execute([
        'drush',
        'cache:rebuild',
        '--yes',
        '--no-interaction',
      ], Argument::type('callable'), $this->projectFixtureDir, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteDrushSqlSanitize(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
      ->execute([
        'drush',
        'sql:sanitize',
        '--yes',
        '--no-interaction',
      ], Argument::type('callable'), $this->projectFixtureDir, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteComposerInstall(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
      ->execute([
        'composer',
        'install',
        '--no-interaction',
      ], Argument::type('callable'), $this->projectFixtureDir, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteRsync(
    ObjectProphecy $local_machine_helper,
    $environments_response,
    ObjectProphecy $process
  ): void {
      $command = [
        'rsync',
        '-rltDvPhe',
        'ssh -o StrictHostKeyChecking=no',
        $environments_response->ssh_url . ':/home/' . RefreshCommand::getSiteGroupFromSshUrl($environments_response) . '/' . $environments_response->name . '/sites/default/files',
        $this->projectFixtureDir . '/docroot/sites/default/',
      ];
      $local_machine_helper->execute($command, Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param object $environments_response
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   * @param $dir
   */
  protected function mockExecuteGitClone(
    ObjectProphecy $local_machine_helper,
    $environments_response,
    ObjectProphecy $process,
    $dir
  ): void {
    $command = [
      'GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=no"',
      'git',
      'clone',
      $environments_response->vcs->url,
      $dir,
    ];
    $command = implode(' ', $command);
    $local_machine_helper->executeFromCmd($command, Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteMySqlDropDb(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
      ->execute([
        'mysql',
        '--host',
        'localhost',
        '--user',
        'drupal',
        '--password=drupal',
        '-e',
        'DROP DATABASE IF EXISTS drupal',
      ], Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteMySqlCreateDb(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
    $local_machine_helper
      ->execute([
        'mysql',
        '--host',
        'localhost',
        '--user',
        'drupal',
        '--password=drupal',
        '-e',
        'create database drupal',
      ], Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $process
   */
  protected function mockExecuteMySqlImport(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $process
  ): void {
// MySQL import command.
    $local_machine_helper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'),
        NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockExecuteDrushExists(
    ObjectProphecy $local_machine_helper
  ): void {
    $local_machine_helper
      ->commandExists('drush')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockExecuteComposerExists(
    ObjectProphecy $local_machine_helper
  ): void {
    $local_machine_helper
      ->commandExists('composer')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockExecutePvExists(
    ObjectProphecy $local_machine_helper
  ): void {
    $local_machine_helper
      ->commandExists('pv')
      ->willReturn(TRUE)
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy|SshHelper $ssh_helper
   * @param object $environments_response
   * @param bool $success
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function mockExecuteSshMySqlDump(
    ObjectProphecy $ssh_helper,
    $environments_response,
    $success
  ): void {
    $process = $this->mockProcess($success);
    $process->getOutput()->willReturn('dbdumpcontents');
    $ssh_helper->executeCommand(
        new EnvironmentResponse($environments_response),
        ['MYSQL_PWD=heWbRncbAfJk6Nx mysqldump --host=fsdb-74.enterprise-g1.hosting.acquia.com --user=s164 profserv201dev | gzip -9'],
        FALSE
      )
      ->willReturn($process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockWriteMySqlDump(
    ObjectProphecy $local_machine_helper
  ): void {
    // Download MySQL dump.
    $local_machine_helper
      ->writeFile(Argument::type('string'), 'dbdumpcontents')
      ->shouldBeCalled();
  }

  /**
   * @return object
   */
  protected function getAcsfEnvResponse() {
    return json_decode(file_get_contents(Path::join($this->fixtureDir, 'acsf_env_response.json')));
  }

  /**
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockAcsfEnvironmentsRequest(
    $applications_response
  ) {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $acsf_env_response = $this->getAcsfEnvResponse();
    $response->sshUrl = $acsf_env_response->sshUrl;
    $response->domains = $acsf_env_response->domains;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param object $applications_response
   *
   * @return object
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function mockEnvironmentsRequest(
    $applications_response
  ) {
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $response->sshUrl = $response->ssh_url;
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$response])
      ->shouldBeCalled();

    return $response;
  }

  /**
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   * @param \Prophecy\Prophecy\ObjectProphecy $dirty_process
   * @param object $environments_response
   */
  protected function mockExecuteGitFetchAndCheckout(
    ObjectProphecy $local_machine_helper,
    ObjectProphecy $dirty_process,
    $environments_response
  ): void {
    $local_machine_helper->execute([
      'git',
      'fetch',
      '--all',
    ], Argument::type('callable'), $this->projectFixtureDir, FALSE)
      ->willReturn($dirty_process->reveal())
      ->shouldBeCalled();
    $local_machine_helper->execute([
      'git',
      'checkout',
      $environments_response->vcs->path,
    ], Argument::type('callable'), $this->projectFixtureDir, FALSE)
      ->willReturn($dirty_process->reveal())
      ->shouldBeCalled();
  }

  /**
   * @param $is_dirty
   * @param \Prophecy\Prophecy\ObjectProphecy $dirty_process
   * @param \Prophecy\Prophecy\ObjectProphecy $local_machine_helper
   */
  protected function mockExecuteGitStatus(
    $is_dirty,
    ObjectProphecy $dirty_process,
    ObjectProphecy $local_machine_helper
  ): void {
    $dirty_process->isSuccessful()->willReturn(!$is_dirty)->shouldBeCalled();
    $dirty_process->getOutput()->willReturn('')->shouldBeCalled();
    $local_machine_helper->execute([
      'git',
      'status',
      '--short',
    ], NULL, $this->projectFixtureDir, FALSE)->willReturn($dirty_process->reveal())->shouldBeCalled();
  }

}
