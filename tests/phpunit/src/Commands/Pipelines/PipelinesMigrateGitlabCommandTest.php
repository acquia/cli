<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Pipelines\PipelinesMigrateGitlabCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class PipelinesMigrateGitlabCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(PipelinesMigrateGitlabCommand::class);
    }

    // Smoke test — replaced by the full version in Task 5.
    #[Group('serial')]
    public function testFullConversionAllEvents(): void
    {
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

    public function testMissingInputFileThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('No acquia-pipelines.yml or acquia-pipelines.yaml file found');
        $this->executeCommand();
    }

    public function testNonExistentPathOptionThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('does not exist');
        $this->executeCommand(['--path' => '/nonexistent/path/abc123']);
    }

    #[Group('serial')]
    public function testYamlExtensionInputProducesYamlOutput(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yaml')
        );

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertFileExists(Path::join($this->projectDir, '.gitlab-ci.yaml'));
        $this->assertFileDoesNotExist(Path::join($this->projectDir, '.gitlab-ci.yml'));
    }

    #[Group('serial')]
    public function testPathOption(): void
    {
        $tempDir = $this->getTempDir();
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($tempDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand(['--path' => $tempDir]);

        $this->assertSame(0, $this->getStatusCode());
        $this->assertFileExists(Path::join($tempDir, '.gitlab-ci.yml'));
        $fs->remove($tempDir);
    }

    #[Group('serial')]
    public function testServicesPhpMysqlComposer(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertSame('php:8.3', $contents['image']);
        $this->assertContains('mysql', $contents['services']);
        $this->assertArrayNotHasKey('_composer', $contents);
    }

    #[Group('serial')]
    public function testVariablesGlobalFlattened(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('variables', $contents);
        $this->assertSame('http://127.0.0.1:8080', $contents['variables']['SIMPLETEST_BASE_URL']);
        $this->assertSame('mysql://root:root@localhost/drupal', $contents['variables']['SIMPLETEST_DB']);
        $this->assertArrayNotHasKey('global', $contents['variables']);
    }

    #[Group('serial')]
    public function testUnknownServiceWarning(): void
    {
        $customContent = "version: 1.0\nservices:\n  - redis\nevents:\n  build:\n    steps:\n      - step1:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $customContent);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('redis', $this->getDisplay());
    }
}
