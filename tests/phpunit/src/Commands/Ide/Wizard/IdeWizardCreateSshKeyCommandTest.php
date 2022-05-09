<?php

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\Commands\Ide\IdeRequiredTestTrait;
use AcquiaCloudApi\Response\IdeResponse;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Path;

/**
 * Class IdeWizardCreateSshKeyCommandTest.
 *
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand $command
 * @package Acquia\Cli\Tests\Ide
 *
 * The IdeWizardCreateSshKeyCommand command is designed to thrown an exception if it
 * is executed from a non Cloud Platform IDE environment. Therefore we do not test Windows
 * compatibility. It should only ever be run in a Linux environment.
 *
 * @requires OS linux|darwin
 */
class IdeWizardCreateSshKeyCommandTest extends IdeWizardTestBase {

  protected $ide;

  /**
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function setUp($output = NULL): void {
    parent::setUp($output);
    $application_response = $this->mockApplicationRequest();
    $this->mockListSshKeysRequest();
    $this->mockPermissionsRequest($application_response);
    $this->ide = $this->mockIdeRequest();
    $this->sshKeyFileName = IdeWizardCreateSshKeyCommand::getSshKeyFilename(self::$remote_ide_uuid);
  }

  /**
   * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
   */
  protected function createCommand(): Command {
    return $this->injectCommand(IdeWizardCreateSshKeyCommand::class);
  }

  /**
   * @return \AcquiaCloudApi\Response\IdeResponse
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function mockIdeRequest(): IdeResponse {
    $ide_response = $this->getMockResponseFromSpec('/ides/{ideUuid}', 'get', '200');
    $this->clientProphecy->request('get', '/ides/' . $this::$remote_ide_uuid)->willReturn($ide_response)->shouldBeCalled();
    return new IdeResponse((object) $ide_response);
  }

  public function testCreate(): void {
    parent::runTestCreate();
  }

  public function testSshKeyAlreadyUploaded(): void {
    parent::runTestSshKeyAlreadyUploaded();
  }

}
