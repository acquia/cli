<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\LinkCommand;
use Acquia\Cli\Command\TelemetryCommand;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;
use Webmozart\PathUtil\Path;

/**
 * Class TelemetryCommandTestBase.
 *
 * @package Acquia\Cli\Tests\Commands
 */
abstract class TelemetryCommandTestBase extends CommandTestBase {

  /**
   * @var string
   */
  protected $legacyAcliConfigFilepath;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $this->legacyAcliConfigFilepath = Path::join($this->dataDir, 'acquia-cli.json');
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->fs->remove($this->legacyAcliConfigFilepath);
  }

}
