<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 * @requires OS linux|darwin
 */
class IdeWizardCreateSshKeyCommandTest extends IdeWizardTestBase {

  protected IdeResponse $ide;

  public function setUp($output = NULL): void {
    parent::setUp($output);
    $application_response = $this->mockApplicationRequest();
    $this->mockListSshKeysRequest();
    $this->mockAccountRequest();
    $this->mockPermissionsRequest($application_response);
    $this->ide = $this->mockIdeRequest();
    $this->sshKeyFileName = IdeWizardCreateSshKeyCommand::getSshKeyFilename(IdeHelper::$remote_ide_uuid);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeWizardCreateSshKeyCommand::class);
  }

  protected function mockIdeRequest(): IdeResponse {
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . IdeHelper::$remote_ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    return new IdeResponse($ide_response);
  }

  public function testCreate(): void {
    parent::runTestCreate();
  }

  public function testSshKeyAlreadyUploaded(): void {
    parent::runTestSshKeyAlreadyUploaded();
  }

}
