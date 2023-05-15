<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class LandoInfoTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  public function testLandoInfoTest(): void {
    $landoInfo = LandoInfoHelper::getLandoInfo();
    $landoInfo->database->creds = [
      'database' => 'drupal9',
      'password' => 'drupal9',
      'user' => 'drupal9',
    ];
    LandoInfoHelper::setLandoInfo($landoInfo);
    $this->assertEquals('drupal9', $this->command->getDefaultLocalDbPassword());
    $this->assertEquals('drupal9', $this->command->getDefaultLocalDbName());
    $this->assertEquals('drupal9', $this->command->getDefaultLocalDbUser());
    $this->assertEquals('database.mynewapp.internal', $this->command->getDefaultLocalDbHost());
    LandoInfoHelper::unsetLandoInfo();
  }

}
