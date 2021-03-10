<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class EnvDbCredsTest
 */
class EnvDbCredsTest extends CommandTestBase {

  /**
   * @var string
   */
  private $dbUser;

  /**
   * @var string
   */
  private $dbPassword;

  /**
   * @var string
   */
  private $dbName;

  /**
   * @var string
   */
  private $dbHost;

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->dbUser = 'myuserisgood';
    $this->dbPassword = 'mypasswordisgreat';
    $this->dbName = 'mynameisgrand';
    $this->dbHost = 'myhostismeh';
    self::setEnvVars($this->dbUser, $this->dbPassword, $this->dbName, $this->dbHost);
  }

  protected function tearDown(): void {
    parent::tearDown();
    self::unsetEnvVars();
  }

  /**
   * @param $db_user
   * @param $db_password
   * @param $db_name
   * @param $db_host
   */
  public static function setEnvVars($db_user, $db_password, $db_name, $db_host): void {
    putenv('ACLI_DB_USER=' . $db_user);
    putenv('ACLI_DB_PASSWORD=' . $db_password);
    putenv('ACLI_DB_NAME=' . $db_name);
    putenv('ACLI_DB_HOST=' . $db_host);
  }

  public static function unsetEnvVars() {
    putenv('ACLI_DB_USER');
    putenv('ACLI_DB_PASSWORD');
    putenv('ACLI_DB_NAME');
    putenv('ACLI_DB_HOST');
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
  public function testEnvDbCreds(): void {
    $this->assertEquals($this->dbUser, $this->command->getLocalDbUser());
    $this->assertEquals($this->dbPassword, $this->command->getLocalDbPassword());
    $this->assertEquals($this->dbName, $this->command->getLocalDbName());
    $this->assertEquals($this->dbHost, $this->command->getLocalDbHost());
  }

}
