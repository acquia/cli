<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;

/**
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
 *     $command
 * @requires OS linux|darwin
 */
class IdeWizardCreateSshKeyCommandTest extends IdeWizardTestBase
{
    public function setUp(): void
    {
        parent::setUp();
        $applicationResponse = $this->mockApplicationRequest();
        $this->mockListSshKeysRequest();
        $this->mockRequest('getAccount');
        $this->mockPermissionsRequest($applicationResponse);
        $this->sshKeyFileName = IdeWizardCreateSshKeyCommand::getSshKeyFilename(IdeHelper::$remoteIdeUuid);
    }

    /**
     * @return \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
     */
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(IdeWizardCreateSshKeyCommand::class);
    }

    public function testCreate(): void
    {
        $this->runTestCreate();
    }

    /**
     * @group brokenProphecy
     */
    public function testSshKeyAlreadyUploaded(): void
    {
        $this->runTestSshKeyAlreadyUploaded();
    }
}
