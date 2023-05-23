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
    $applicationResponse = $this->mockApplicationRequest();
    $this->mockListSshKeysRequest();
    $this->mockRequest('/account');
    $this->mockPermissionsRequest($applicationResponse);
    $this->ide = $this->mockIdeRequest();
    $this->sshKeyFileName = IdeWizardCreateSshKeyCommand::getSshKeyFilename(IdeHelper::$remoteIdeUuid);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeWizardCreateSshKeyCommand::class);
  }

  protected function mockIdeRequest(): IdeResponse {
    $ideResponse = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . IdeHelper::$remoteIdeUuid)->willReturn($ideResponse)->shouldBeCalled();
    return new IdeResponse($ideResponse);
  }

  public function testCreate(): void {
    parent::runTestCreate();
  }

  public function testSshKeyAlreadyUploaded(): void {
    parent::runTestSshKeyAlreadyUploaded();
  }

}
