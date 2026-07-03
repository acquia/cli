<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand;
use Acquia\Cli\Tests\Commands\Ide\IdeHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;

/**
 * @property \Acquia\Cli\Command\Ide\Wizard\IdeWizardCreateSshKeyCommand
 *     $command
 */
#[RequiresOperatingSystem('linux|darwin')]
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

    #[Group('brokenProphecy')]
    public function testSshKeyAlreadyUploaded(): void
    {
        $this->runTestSshKeyAlreadyUploaded();
    }

    public function testSshKeyCodebaseUuidExists(): void
    {
        $this->runTestSshKeyCodebaseUuidExists();
    }

    public function testPromptWaitForSshReturnsTrue(): void
    {
        $this->runTestPromptWaitForSshReturnsTrue();
    }

    public function testPromptWaitForSshReturnsFalse(): void
    {
        $this->runTestPromptWaitForSshReturnsFalse();
    }

    #[Group('brokenProphecy')]
    public function testIdeWizardCreateSshKeyCommandHelpContainsIdeHelperText(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('This command will only work in an IDE terminal.', $help);
    }
}
