<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Command\Command;

/**
 * Class IdeDeleteCommandTest.
 *
 * @property IdeDeleteCommand $command
 * @package Acquia\Cli\Tests\Ide
 */
class IdeDeleteCommandTest extends CommandTestBase {

  /**
   * This method is called before each test.
   *
   * @param null $output
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    parent::setUp();
    $this->getCommandTester();
    $this->application->addCommands([
      $this->injectCommand(SshKeyDeleteCommand::class),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeDeleteCommand::class);
  }

  /**
   * Tests the 'ide:delete' command.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testIdeDeleteCommand(): void {

    $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();

    $ide_uuid = '9a83c081-ef78-4dbd-8852-11cc3eb248f7';
    $ide_delete_response = $this->mockIdeDeleteRequest($ide_uuid);
    $ide_get_response = $this->mockGetIdeRequest($ide_uuid);
    $ide = new IdeResponse((object) $ide_get_response);
    $ssh_key_get_response = $this->mockListSshKeysRequestWithIdeKey($ide);

    $this->mockGetIdeSshKeyRequest($ide);
    $this->mockDeleteSshKeyRequest($ssh_key_get_response->{'_embedded'}->items[0]->uuid);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Please select the application for which you'd like to create a new IDE.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Please select the IDE you'd like to delete:
      0,
      // Would you like to delete the SSH key associated with this IDE from your Cloud Platform account?
      'y',
    ];

    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString($ide_delete_response->{'De-provisioning IDE'}->value->message, $output);
  }

}
