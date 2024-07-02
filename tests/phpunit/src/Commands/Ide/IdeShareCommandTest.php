<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ide\IdeShareCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @property IdeShareCommand $command
 */
class IdeShareCommandTest extends CommandTestBase
{
    use IdeRequiredTestTrait;

    /**
     * @var array<mixed>
     */
    private array $shareCodeFilepaths;

    private string $shareCode;

    /**
     * This method is called before each test.
     */
    public function setUp(OutputInterface $output = null): void
    {
        parent::setUp();
        $this->shareCode = 'a47ac10b-58cc-4372-a567-0e02b2c3d470';
        $shareCodeFilepath = $this->fs->tempnam(sys_get_temp_dir(), 'acli_share_uuid_');
        $this->fs->dumpFile($shareCodeFilepath, $this->shareCode);
        $this->command->setShareCodeFilepaths([$shareCodeFilepath]);
        IdeHelper::setCloudIdeEnvVars();
    }

    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(IdeShareCommand::class);
    }

    public function testIdeShareCommand(): void
    {
        $this->executeCommand();
        $output = $this->getDisplay();
        $this->assertStringContainsString('Your IDE Share URL: ', $output);
        $this->assertStringContainsString($this->shareCode, $output);
    }

    public function testIdeShareRegenerateCommand(): void
    {
        $this->executeCommand(['--regenerate' => true]);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Your IDE Share URL: ', $output);
        $this->assertStringNotContainsString($this->shareCode, $output);
    }
}
