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

        // Stages.
        $this->assertSame(
            ['build', 'fail-on-build', 'post-deploy', 'pr-merged', 'pr-closed'],
            $contents['stages']
        );

        // Image and services from fixture.
        $this->assertSame('php:8.3', $contents['image']);
        $this->assertContains('mysql', $contents['services']);

        // Variables section.
        $this->assertArrayHasKey('variables', $contents);
        $this->assertSame('http://127.0.0.1:8080', $contents['variables']['SIMPLETEST_BASE_URL']);
        $this->assertSame('mysql://root:root@localhost/drupal', $contents['variables']['SIMPLETEST_DB']);

        // Build jobs exist and have correct stage.
        $this->assertArrayHasKey('setup', $contents);
        $this->assertSame('build', $contents['setup']['stage']);
        $this->assertArrayHasKey('script', $contents['setup']);

        // Composer install added as before_script on build jobs.
        $this->assertArrayHasKey('before_script', $contents['setup']);
        $this->assertContains('composer install', $contents['setup']['before_script']);

        // fail-on-build jobs have when: on_failure.
        $this->assertArrayHasKey('notify', $contents);
        $this->assertSame('fail-on-build', $contents['notify']['stage']);
        $this->assertSame('on_failure', $contents['notify']['when']);

        // pr-merged jobs have rules.
        $this->assertArrayHasKey('cleanup', $contents);
        $this->assertSame('pr-merged', $contents['cleanup']['stage']);
        $this->assertArrayHasKey('rules', $contents['cleanup']);
        $this->assertSame('$CI_PIPELINE_SOURCE == "merge_request_event"', $contents['cleanup']['rules'][0]['if']);
        $this->assertSame('on_success', $contents['cleanup']['rules'][0]['when']);

        // pr-closed jobs have rules.
        $this->assertArrayHasKey('teardown', $contents);
        $this->assertSame('pr-closed', $contents['teardown']['stage']);
        $this->assertArrayHasKey('rules', $contents['teardown']);
        $this->assertSame('$CI_PIPELINE_SOURCE == "merge_request_event"', $contents['teardown']['rules'][0]['if']);
        $this->assertSame('manual', $contents['teardown']['rules'][0]['when']);

        // Source file not deleted.
        $this->assertFileExists(Path::join($this->projectDir, 'acquia-pipelines.yml'));
    }

    public function testMissingInputFileThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/No acquia-pipelines\.yml or acquia-pipelines\.yaml file found/');
        $this->executeCommand();
    }

    public function testNonExistentPathOptionThrows(): void
    {
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/does not exist/');
        $this->executeCommand(['--path' => '/nonexistent/path/abc123']);
    }

    #[Group('serial')]
    public function testYmlExtensionInputProducesYmlOutput(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertFileExists(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertFileDoesNotExist(Path::join($this->projectDir, '.gitlab-ci.yaml'));
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

    #[Group('serial')]
    public function testFailOnBuildJobsHaveWhenOnFailure(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertSame('on_failure', $contents['notify']['when']);
        $this->assertSame('fail-on-build', $contents['notify']['stage']);
    }

    #[Group('serial')]
    public function testPrMergedAndPrClosedRules(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));

        $this->assertArrayHasKey('rules', $contents['cleanup']);
        $this->assertSame('$CI_PIPELINE_SOURCE == "merge_request_event"', $contents['cleanup']['rules'][0]['if']);
        $this->assertSame('on_success', $contents['cleanup']['rules'][0]['when']);

        $this->assertArrayHasKey('rules', $contents['teardown']);
        $this->assertSame('$CI_PIPELINE_SOURCE == "merge_request_event"', $contents['teardown']['rules'][0]['if']);
        $this->assertSame('manual', $contents['teardown']['rules'][0]['when']);
    }

    #[Group('serial')]
    public function testStepWithEmptyScriptIsSkipped(): void
    {
        $fs = new Filesystem();
        $customContent = "version: 1.0\nevents:\n  build:\n    steps:\n      - empty-step:\n          type: script\n          script: []\n      - real-step:\n          type: script\n          script:\n            - echo hi\n";
        $fs->dumpFile(Path::join($this->projectDir, 'acquia-pipelines.yml'), $customContent);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayNotHasKey('empty-step', $contents);
        $this->assertArrayHasKey('real-step', $contents);
    }

    #[Group('serial')]
    public function testMissingEventsKeyThrows(): void
    {
        $fs = new Filesystem();
        $fs->dumpFile(
            Path::join($this->projectDir, 'acquia-pipelines.yml'),
            "version: 1.0\nservices:\n  - php:\n      version: '8.1'\n"
        );

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches("/does not contain an 'events' key/");
        $this->executeCommand();
    }

    #[Group('serial')]
    public function testSourceFileIsNotDeleted(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $this->assertFileExists(Path::join($this->projectDir, 'acquia-pipelines.yml'));
    }

    #[Group('serial')]
    public function testExistingOutputFileIsOverwritten(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );
        $fs->dumpFile(Path::join($this->projectDir, '.gitlab-ci.yml'), 'old: content');

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('stages', $contents);
        $this->assertStringContainsString('overwritten', $this->getDisplay());
    }

    #[Group('serial')]
    public function testPrMergedAndPrClosedJobsHaveYamlComments(): void
    {
        $fs = new Filesystem();
        $fs->copy(
            Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
            Path::join($this->projectDir, 'acquia-pipelines.yml')
        );

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $outputFile = Path::join($this->projectDir, '.gitlab-ci.yml');
        $raw = file_get_contents($outputFile);
        $this->assertStringContainsString('# TODO: Adjust rule', $raw);
        $this->assertStringContainsString('# TODO: GitLab has no native pipeline trigger', $raw);
    }

    #[Group('serial')]
    public function testEmptyInputFileThrows(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), '');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/empty or unreadable/');
        $this->executeCommand();
    }

    #[Group('serial')]
    public function testInvalidYamlInputThrows(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), "invalid: yaml: [\nbad");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/Failed to parse/');
        $this->executeCommand();
    }
}
