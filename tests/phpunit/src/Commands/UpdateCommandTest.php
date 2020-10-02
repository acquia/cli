<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\Ide\IdeListCommand;
use Acquia\Cli\Command\UpdateCommand;
use Acquia\Cli\SelfUpdate\Strategy\GithubStrategy;
use Acquia\Cli\Tests\CommandTestBase;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Webmozart\PathUtil\Path;

/**
 * Class UpdateCommandTest.
 *
 * @property \Acquia\Cli\Command\UpdateCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class UpdateCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(UpdateCommand::class);
  }

  /**
   *
   */
  public function testNonPharException(): void {
    try {
      $this->executeCommand([], []);
    }
    catch (Exception $e) {
      $this->assertStringContainsString('update only works when running the phar version of ', $e->getMessage());
    }
  }

  /**
   * Tests the 'ide:list' command for "Update" message.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeListCommandForUpdateMessage(): void {
    $this->command = $this->injectCommand(IdeListCommand::class);
    $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();
    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application.
      0,
      // Would you like to link the project at ... ?
      'y',
    ];
    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();

    $this->assertStringContainsString('A newer version of Acquia CLI is available. Run acli self-update to update to ', $output);
  }

  /**
   * @requires OS linux|darwin
   *
   * @throws \Exception
   */
  public function testDownloadUpdate(): void {
    $stub_phar = $this->fs->tempnam(sys_get_temp_dir(), 'acli_phar');
    $this->fs->chmod($stub_phar, 0751);
    $original_file_perms = fileperms($stub_phar);
    $this->updateHelper->setPharPath($stub_phar);

    $args = [
      '--allow-unstable' => '',
    ];
    $this->executeCommand($args, []);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Updated from UNKNOWN to v1.0.0-beta3', $output);
    $this->assertFileExists($stub_phar);

    // The file permissions on the new phar should be the same as on the old phar.
    $this->assertEquals($original_file_perms, fileperms($stub_phar) );
  }

  public function testDownloadProgressDisplay(): void {
    $output = new BufferedOutput();
    $progress = NULL;
    GithubStrategy::displayDownloadProgress(100, 0, $progress, $output);
    $this->assertStringContainsString('0/100 [💧---------------------------]   0%', $output->fetch());

    // Need to sleep to prevent the default redraw frequency from skipping display.
    sleep(1);
    GithubStrategy::displayDownloadProgress(100, 50, $progress, $output);
    $this->assertStringContainsString('50/100 [==============💧-------------]  50%', $output->fetch());

    GithubStrategy::displayDownloadProgress(100, 100, $progress, $output);
    $this->assertStringContainsString('100/100 [============================] 100%', $output->fetch());
  }

  /**
   * @return string
   */
  protected function createPharStub(): string {
    $stub_phar = $this->fs->tempnam(sys_get_temp_dir(), 'acli_phar');
    $this->fs->chmod($stub_phar, 0751);
    $this->command->setPharPath($stub_phar);
    return $stub_phar;
  }

}
