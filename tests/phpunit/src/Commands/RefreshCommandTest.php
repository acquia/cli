<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\RefreshCommand;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

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
    return new RefreshCommand();
  }

  /**
   * Tests the 'refresh' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testRefreshCommand(): void {
    $this->setCommand($this->createCommand());
    $cloud_client = $this->getMockClient();
    $applications_response = $this->mockApplicationsRequest($cloud_client);
    // Request for Environments data. This isn't actually the endpoint we should
    // be using, but we do it due to CXAPI-7209.
    $environments_response = $this->getMockResponseFromSpec('/environments/{environmentId}',
      'get', '200');
    $cloud_client->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([$environments_response])
      ->shouldBeCalled();
    $databases_response = $this->getMockResponseFromSpec('/environments/{environmentId}/databases',
      'get', '200');
    $cloud_client->request('get',
      "/environments/{$environments_response->id}/databases")
      ->willReturn($databases_response->_embedded->items)
      ->shouldBeCalled();

    $process = $this->prophet->prophesize(Process::class);
    $process->isSuccessful()->willReturn(TRUE);
    $process->getExitCode()->willReturn(0);
    $process->getOutput()->willReturn('dbdumpcontents');

    $local_machine_helper = $this->prophet->prophesize(LocalMachineHelper::class);
    $local_machine_helper->useTty()->willReturn(FALSE);

    $command = 'MYSQL_PWD=supersecretdbpassword1! mysqldump --host=dbhost.example.com --user=my_db_user my_db | gzip -9';
    $local_machine_helper
      ->runCommandViaSsh($environments_response->ssh_url, $command)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $command = [
       'mysql',
       '--host',
       'localhost',
       '--user',
       'drupal',
       '--password=drupal',
       '-e',
       'DROP DATABASE IF EXISTS drupal',
    ];
    $local_machine_helper
      ->execute($command, Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();
    $command = [
      0 => 'mysql',
      1 => '--host',
      2 => 'localhost',
      3 => '--user',
      4 => 'drupal',
      5 => '--password=drupal',
      6 => '-e',
      7 => 'create database drupal',
    ];
    $local_machine_helper
      ->execute($command, Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $local_machine_helper
      ->commandExists('pv')
      ->willReturn(TRUE)
      ->shouldBeCalled();

    // MySQL import command.
    $local_machine_helper
      ->executeFromCmd(Argument::type('string'), Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $command = [
      0 => 'git',
      1 => 'clone',
      2 => $environments_response->vcs->url,
      3 => $this->projectFixtureDir,
    ];
    $local_machine_helper
      ->execute($command, Argument::type('callable'))
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $command = [
      0 => 'rsync',
      1 => '-rve',
      2 => 'ssh -o StrictHostKeyChecking=no',
      3 => $environments_response->ssh_url . ':/' . $environments_response->name . '/sites/default/files',
      4 => $this->projectFixtureDir . '/docroot/sites/default',
    ];

    $local_machine_helper
      ->execute($command, Argument::type('callable'), NULL, FALSE)
      ->willReturn($process->reveal())
      ->shouldBeCalled();

    $local_machine_helper
      ->commandExists('drush')
      ->willReturn(FALSE)
      ->shouldBeCalled();

    // Download MySQL dump.
    $local_machine_helper
      ->writeFile(Argument::type('string'), 'dbdumpcontents')
      ->shouldBeCalled();

    // Set up file system.
    $local_machine_helper
      ->getFilesystem()
      ->willReturn($this->fs)
      ->shouldBeCalled();

    $this->application->setLocalMachineHelper($local_machine_helper->reveal());
    $this->application->setAcquiaCloudClient($cloud_client->reveal());
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'y',
      // Please select an Acquia Cloud application:
      0,
      // Please choose an Acquia environment:
      0,
      // Choose a database to copy:
      0,
    ];
    $this->executeCommand([], $inputs);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('Please select an Acquia Cloud application:', $output);
    $this->assertStringContainsString('[0] Sample application 1', $output);
    $this->assertStringContainsString('Choose an Acquia Cloud environment to copy from:', $output);
    $this->assertStringContainsString('[0] Dev (vcs: master)', $output);
    $this->assertStringContainsString('Choose a database to copy:', $output);
    $this->assertStringContainsString('[0] my_db (default)', $output);
  }

}
