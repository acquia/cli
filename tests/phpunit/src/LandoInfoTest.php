<?php

namespace Acquia\Cli\Tests;

use Acquia\Cli\Command\ClearCacheCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Class LandoInfoTest
 */
class LandoInfoTest extends CommandTestBase {

  public function setUp($output = NULL): void {
    parent::setUp();
    self::setLandoInfo();
  }

  protected function tearDown(): void {
    parent::tearDown();
    self::unsetLandoInfo();
  }

  public static function setLandoInfo() {
    putenv('LANDO_INFO={"appserver":{"service":"appserver","urls":["http://my-new-app.lndo.site:8000/","https://my-new-app.lndo.site/"],"type":"php","healthy":true,"via":"apache","webroot":"docroot","config":{"php":"/Users/matthew.grasmick/.lando/config/drupal9/php.ini"},"version":"7.3","meUser":"www-data","hasCerts":true,"hostnames":["appserver.mynewapp.internal"]},"database":{"service":"database","urls":[],"type":"mysql","healthy":true,"internal_connection":{"host":"database","port":"3306"},"external_connection":{"host":"127.0.0.1","port":true},"healthcheck":"bash -c \"[ -f /bitnami/mysql/.mysql_initialized ]\"","creds":{"database":"drupal9","password":"drupal9","user":"drupal9"},"config":{"database":"/Users/matthew.grasmick/.lando/config/drupal9/mysql.cnf"},"version":"5.7","meUser":"www-data","hasCerts":false,"hostnames":["database.mynewapp.internal"]}}');
  }

  public static function unsetLandoInfo() {
    putenv('LANDO_INFO');
  }

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
    $this->assertEquals('drupal9', $this->command->getLocalDbPassword());
    $this->assertEquals('drupal9', $this->command->getLocalDbName());
    $this->assertEquals('drupal9', $this->command->getLocalDbUser());
    $this->assertEquals('database.mynewapp.internal', $this->command->getLocalDbHost());
  }

}
