<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeShareCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeShareCommandTest.
 *
 * @property IdeShareCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeShareCommandTest extends CommandTestBase {

  use IdeRequiredTestTrait;

  /**
   * @var array
   */
  private array $shareCodeFilepaths;

  private string $shareCode;

  /**
   * This method is called before each test.
   */
  public function setUp(OutputInterface $output = NULL): void {
    parent::setUp();
    $this->shareCode = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
    $shareCodeFilepath = $this->fs->tempnam(sys_get_temp_dir(), 'acli_share_uuid_');
    $this->fs->dumpFile($shareCodeFilepath, $this->shareCode);
    $this->command->setShareCodeFilepaths([$shareCodeFilepath]);
    IdeHelper::setCloudIdeEnvVars();
  }

  protected function createCommand(): Command {
    return $this->injectCommand(IdeShareCommand::class);
  }

  /**
   * Tests the 'ide:share' command.
   */
  public function testIdeShareCommand(): void {
    $ide_get_response = $this->mockGetIdeRequest(IdeHelper::$remote_ide_uuid);
    $ide = new IdeResponse((object) $ide_get_response);
    $this->executeCommand([], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Your IDE Share URL: ', $output);
    $this->assertStringContainsString($this->shareCode, $output);
  }

  /**
   * Tests the 'ide:share' command.
   */
  public function testIdeShareRegenerateCommand(): void {
    $ide_get_response = $this->mockGetIdeRequest(IdeHelper::$remote_ide_uuid);
    $ide = new IdeResponse((object) $ide_get_response);
    $this->executeCommand(['--regenerate' => TRUE], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString('Your IDE Share URL: ', $output);
    $this->assertStringNotContainsString($this->shareCode, $output);
  }

}
