<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class LandoInfoTest
 */
class LandoInfoTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  public function testLandoInfoTest(): void {
    $lando_info = LandoInfoHelper::getLandoInfo();
    $lando_info->database->creds = [
      'database' => 'drupal9',
      'password' => 'drupal9',
      'user' => 'drupal9',
    ];
    LandoInfoHelper::setLandoInfo($lando_info);
    $this->assertEquals('drupal9', $this->command->getLocalDbPassword());
    $this->assertEquals('drupal9', $this->command->getLocalDbName());
    $this->assertEquals('drupal9', $this->command->getLocalDbUser());
    $this->assertEquals('database.mynewapp.internal', $this->command->getLocalDbHost());
    LandoInfoHelper::unsetLandoInfo();
  }

}
