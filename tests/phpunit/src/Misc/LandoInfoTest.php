<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class LandoInfoTest
 */
class LandoInfoTest extends CommandTestBase {

  use LandoInfoTrait;

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  /**
   *
   */
  public function testLandoInfoTest(): void {
    $lando_info = LandoInfoTrait::getLandoInfo();
    $lando_info->database->creds = [
      'database' => 'drupal9',
      'password' => 'drupal9',
      'user' => 'drupal9',
    ];
    LandoInfoTrait::setLandoInfo($lando_info);
    $this->assertEquals('drupal9', $this->command->getLocalDbPassword());
    $this->assertEquals('drupal9', $this->command->getLocalDbName());
    $this->assertEquals('drupal9', $this->command->getLocalDbUser());
    $this->assertEquals('database.mynewapp.internal', $this->command->getLocalDbHost());
    LandoInfoTrait::unsetLandoInfo();
  }

}
