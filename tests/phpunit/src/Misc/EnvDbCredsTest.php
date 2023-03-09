<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class EnvDbCredsTest
 */
class EnvDbCredsTest extends CommandTestBase {

  private string $dbUser;

  private string $dbPassword;

  private string $dbName;

  private string $dbHost;

  public function setUp($output = NULL): void {
    parent::setUp();
    $this->dbUser = 'myuserisgood';
    $this->dbPassword = 'mypasswordisgreat';
    $this->dbName = 'mynameisgrand';
    $this->dbHost = 'myhostismeh';
    TestBase::setEnvVars($this->getEnvVars());
  }

  public function tearDown(): void {
    parent::tearDown();
    TestBase::unsetEnvVars($this->getEnvVars());
  }

  protected function getEnvVars() {
    return [
      'ACLI_DB_USER' => $this->dbUser,
      'ACLI_DB_PASSWORD' => $this->dbPassword,
      'ACLI_DB_NAME' => $this->dbName,
      'ACLI_DB_HOST' => $this->dbHost,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  public function testEnvDbCreds(): void {
    $this->assertEquals($this->dbUser, $this->command->getDefaultLocalDbUser());
    $this->assertEquals($this->dbPassword, $this->command->getDefaultLocalDbPassword());
    $this->assertEquals($this->dbName, $this->command->getDefaultLocalDbName());
    $this->assertEquals($this->dbHost, $this->command->getDefaultLocalDbHost());
  }

}
