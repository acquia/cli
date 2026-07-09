<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pipelines\PipelinesMigrateGitlabCommand;
use Acquia\Cli\Tests\CommandTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class PipelinesMigrateGitlabCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(PipelinesMigrateGitlabCommand::class);
  }

  // Smoke test — replaced by the full version in Task 5.

  #[Group('serial')]
    public function testFullConversionAllEvents(): void {
    $fs = new Filesystem();
    $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

    $this->executeCommand();

    $this->assertSame(0, $this->getStatusCode());
    $outputFile = Path::join($this->projectDir, '.gitlab-ci.yml');
    $this->assertFileExists($outputFile);
    $contents = Yaml::parseFile($outputFile);
    $this->assertArrayHasKey('stages', $contents);
    $this->assertContains('build', $contents['stages']);
    }

}
