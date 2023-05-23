<?php

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\Ide\IdeDeleteCommand;
use Acquia\Cli\Command\Ssh\SshKeyDeleteCommand;
use Acquia\Cli\Tests\CommandTestBase;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property IdeDeleteCommand $command
 */
class IdeDeleteCommandTest extends CommandTestBase {

  /**
   * This method is called before each test.
   */
  public function setUp(OutputInterface $output = NULL): void {
    parent::setUp();
    $this->getCommandTester();
    $this->application->addCommands([
      $this->injectCommand(SshKeyDeleteCommand::class),
    ]);
  }

  protected function createCommand(): Command {
    return $this->injectCommand(IdeDeleteCommand::class);
  }

  public function testIdeDeleteCommand(): void {

    $this->mockRequest('getApplications');
    $this->mockApplicationRequest();
    $this->mockIdeListRequest();

    $ideUuid = '9a83c081-ef78-4dbd-8852-11cc3eb248f7';
    $ideDeleteResponse = $this->mockIdeDeleteRequest($ideUuid);
    $ideGetResponse = $this->mockGetIdeRequest($ideUuid);
    $ide = new IdeResponse((object) $ideGetResponse);
    $sshKeyGetResponse = $this->mockListSshKeysRequestWithIdeKey($ide);

    $this->mockDeleteSshKeyRequest($sshKeyGetResponse->{'_embedded'}->items[0]->uuid);

    $inputs = [
      // Would you like Acquia CLI to search for a Cloud application that matches your local git config?
      'n',
      // Select the application for which you'd like to create a new IDE.
      0,
      // Would you like to link the project at ... ?
      'y',
      // Select the IDE you'd like to delete:
      0,
      // Would you like to delete the SSH key associated with this IDE from your Cloud Platform account?
      'y',
    ];

    $this->executeCommand([], $inputs);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString($ideDeleteResponse->{'De-provisioning IDE'}->value->message, $output);
  }

}
