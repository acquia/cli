<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\Command\Self\ClearCacheCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Acquia\Cli\Tests\TestBase;
use Symfony\Component\Console\Command\Command;

class EnvDbCredsTest extends CommandTestBase {

  private string $dbUser;

  private string $dbPassword;

  private string $dbName;

  private string $dbHost;

  public function setUp(mixed $output = NULL): void {
    $this->dbUser = 'myuserisgood';
    $this->dbPassword = 'mypasswordisgreat';
    $this->dbName = 'mynameisgrand';
    $this->dbHost = 'myhostismeh';
    TestBase::setEnvVars($this->getEnvVars());
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
    TestBase::unsetEnvVars($this->getEnvVars());
  }

  /**
   * @return array<string>
   */
  protected function getEnvVars(): array {
    return [
      'ACLI_DB_HOST' => $this->dbHost,
      'ACLI_DB_NAME' => $this->dbName,
      'ACLI_DB_PASSWORD' => $this->dbPassword,
      'ACLI_DB_USER' => $this->dbUser,
    ];
  }

  protected function createCommand(): Command {
    return $this->injectCommand(ClearCacheCommand::class);
  }

  public function testEnvDbCreds(): void {
    $this->assertEquals($this->dbUser, $this->command->getLocalDbUser());
    $this->assertEquals($this->dbPassword, $this->command->getLocalDbPassword());
    $this->assertEquals($this->dbName, $this->command->getLocalDbName());
    $this->assertEquals($this->dbHost, $this->command->getLocalDbHost());
  }

}
