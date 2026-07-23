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

    public function testYmlExtensionIsDetected(): void
    {
        $content = file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertFileExists(Path::join($this->projectDir, '.gitlab-ci.yml'));
    }

    public function testYamlExtensionIsDetected(): void
    {
        $content = file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yaml'), $content);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertFileExists(Path::join($this->projectDir, '.gitlab-ci.yaml'));
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

    public function testSuccessMessageIsEmitted(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $this->assertStringContainsString('Migration complete', $this->getDisplay());
    }

    public function testServicesPhpMapsToImage(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertSame('php:8.3', $output['image']);
    }

    public function testServicesMysqlMapsToServices(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertContains('mysql', $output['services']);
    }

    public function testComposerServiceAddsBeforeScript(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        // 'setup' is in the build stage and fixture has composer service.
        $this->assertArrayHasKey('before_script', $output['setup']);
        $this->assertContains('composer install', $output['setup']['before_script']);
    }

    public function testNoComposerServiceNoBeforeScript(): void
    {
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - myjob:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayNotHasKey('before_script', $output['myjob']);
    }

    public function testVariablesGlobalIsFlattenedNonSerial(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('variables', $output);
        $this->assertSame('http://127.0.0.1:8080', $output['variables']['SIMPLETEST_BASE_URL']);
        $this->assertArrayNotHasKey('global', $output['variables']);
    }

    public function testNoVariablesSectionProducesNoVariablesKey(): void
    {
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - myjob:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayNotHasKey('variables', $output);
    }

    public function testVariablesWithoutGlobalWrapperPassedThrough(): void
    {
        $yaml = "version: 1.0\nvariables:\n  MY_VAR: value\nevents:\n  build:\n    steps:\n      - myjob:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('variables', $output);
        $this->assertSame('value', $output['variables']['MY_VAR']);
    }

    public function testStagesArePopulated(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('stages', $output);
        $this->assertNotEmpty($output['stages']);
        $this->assertContains('build', $output['stages']);
    }

    public function testJobsArePopulated(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('setup', $output);
        $this->assertSame('build', $output['setup']['stage']);
    }

    public function testFailOnBuildJobHasWhenOnFailure(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertSame('on_failure', $output['notify']['when']);
    }

    public function testPrMergedJobHasRules(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('rules', $output['cleanup']);
        $this->assertSame('on_success', $output['cleanup']['rules'][0]['when']);
    }

    public function testPrClosedJobHasRules(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('rules', $output['teardown']);
        $this->assertSame('manual', $output['teardown']['rules'][0]['when']);
    }

    public function testPrClosedTodoCommentIsInjected(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $raw = (string) file_get_contents(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertStringContainsString('# TODO: GitLab has no native pipeline trigger', $raw);
    }

    public function testPrMergedTodoCommentIsInjected(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $raw = (string) file_get_contents(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertStringContainsString('# TODO: Adjust rule', $raw);
    }

    public function testWhitespaceOnlyFileThrows(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), "   \n\t  \n");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/empty or unreadable/');
        $this->executeCommand();
    }

    public function testUnknownServiceWarningNonSerial(): void
    {
        $yaml = "version: 1.0\nservices:\n  - redis\nevents:\n  build:\n    steps:\n      - step1:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('redis', $this->getDisplay());
    }

    public function testEmptyInputFileThrowsNonSerial(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), '');
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/empty or unreadable/');
        $this->executeCommand();
    }

    public function testInvalidYamlInputThrowsNonSerial(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), "invalid: yaml: [\nbad");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches('/Failed to parse/');
        $this->executeCommand();
    }

    public function testOverwriteWarningIsEmitted(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);
        file_put_contents(Path::join($this->projectDir, '.gitlab-ci.yml'), 'old: content');

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('overwritten', $this->getDisplay());
    }

    public function testInvalidYamlExceptionContainsBothMessageAndParseError(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), "invalid: yaml: [\nbad");
        try {
            $this->executeCommand();
            $this->fail('Expected AcquiaCliException was not thrown.');
        } catch (AcquiaCliException $e) {
            // Must contain "Failed to parse" prefix.
            $this->assertStringContainsString('Failed to parse', $e->getMessage());
            // Must start with "Failed to parse" (Concat mutant reverses the order).
            $this->assertStringStartsWith('Failed to parse', $e->getMessage());
            // Message must be longer than "Failed to parse <path>: " alone; $e->getMessage() adds parse details.
            $this->assertGreaterThan(strlen('Failed to parse ' . Path::join($this->projectDir, 'acquia-pipelines.yml') . ': '), strlen($e->getMessage()));
        }
    }

    public function testEventsKeyWithScalarValueThrows(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), "version: 1.0\nevents: \"not-a-mapping\"\n");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches("/is not a mapping/");
        $this->executeCommand();
    }

    public function testPhpServiceWithNoVersionEmitsWarning(): void
    {
        $yaml = "version: 1.0\nservices:\n  - php\nevents:\n  build:\n    steps:\n      - myjob:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('no version specified', $this->getDisplay());
        // Without a version, 'image' must NOT be set to 'php:'.
        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayNotHasKey('image', $output);
    }

    public function testValidYamlWithoutEventsKeyThrows(): void
    {
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), "version: 1.0\nservices:\n  - php\n");
        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessageMatches("/does not contain an 'events' key/");
        $this->executeCommand();
    }

    public function testVariablesMigrationMessageIsEmitted(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $this->assertStringContainsString("Migrated 'variables' section.", $this->getDisplay());
    }

    public function testIntegerServiceEntryIsSkippedWithWarningAndSubsequentServicesProcessed(): void
    {
        // YAML integer list item: 42 is not string and not array, should trigger warning path.
        // The 'continue' (not 'break') must allow subsequent mysql service to still be processed.
        $yaml = "version: 1.0\nservices:\n  - 42\n  - mysql\nevents:\n  build:\n    steps:\n      - myjob:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('malformed', $this->getDisplay());
        // The subsequent mysql service must still be migrated (continue, not break).
        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('services', $output);
        $this->assertContains('mysql', $output['services']);
    }

    public function testMultipleVariablesWithoutGlobalAreAllCopied(): void
    {
        $yaml = "version: 1.0\nvariables:\n  VAR_A: alpha\n  VAR_B: beta\nevents:\n  build:\n    steps:\n      - myjob:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('variables', $output);
        $this->assertSame('alpha', $output['variables']['VAR_A']);
        $this->assertSame('beta', $output['variables']['VAR_B']);
    }

    public function testEventsAfterMissingEventAreStillProcessed(): void
    {
        // Has build and post-deploy but NOT fail-on-build. The continue (not break) in the
        // EVENT_STAGE_MAP loop means post-deploy must still be processed.
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - setup:\n          script:\n            - echo build\n  post-deploy:\n    steps:\n      - deploy:\n          script:\n            - echo deploy\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('deploy', $output);
        $this->assertSame('post-deploy', $output['deploy']['stage']);
    }

    public function testEventWithAllStepsSkippedAddsNoStage(): void
    {
        // Build event has a step with no script — all steps skipped, so the build stage
        // should NOT be added to stages (eventHasJob remains false).
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - empty-job:\n          type: script\n          script: []\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $stages = $output['stages'] ?? [];
        $this->assertNotContains('build', $stages);
    }

    public function testMalformedStepStringIsSkippedWithWarningNonSerial(): void
    {
        // A step that is a string (not an array) should be skipped with a warning.
        // The continue (not break) must allow the subsequent real-step to still be processed.
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - bad-string-step\n      - real-step:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('Malformed step', $this->getDisplay());
        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('real-step', $output);
        $this->assertArrayNotHasKey('bad-string-step', $output);
    }

    public function testStepWithoutScriptKeyIsSkippedWithWarningNonSerial(): void
    {
        // A step with type but no script should be skipped with a warning.
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - no-script:\n          type: script\n      - with-script:\n          script:\n            - echo hi\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('has no script', $this->getDisplay());
        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayNotHasKey('no-script', $output);
        $this->assertArrayHasKey('with-script', $output);
    }

    public function testJobsContainScriptProperty(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $output = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
        $this->assertArrayHasKey('script', $output['setup']);
        $this->assertNotEmpty($output['setup']['script']);
    }

    public function testDuplicateStepNameEmitsWarning(): void
    {
        // Two events with a step of the same name — duplicate warning should be emitted.
        $yaml = "version: 1.0\nevents:\n  build:\n    steps:\n      - shared-step:\n          script:\n            - echo build\n  post-deploy:\n    steps:\n      - shared-step:\n          script:\n            - echo deploy\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringContainsString('multiple events', $this->getDisplay());
    }

    public function testUniqueStepNamesProduceNoDuplicateWarning(): void
    {
        // No duplicate step names — the "multiple events" warning must NOT appear.
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $this->assertStringNotContainsString('multiple events', $this->getDisplay());
    }

    public function testEventMigrationMessageIsEmitted(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $this->assertStringContainsString("Migrated 'build' event.", $this->getDisplay());
    }

    public function testJobNameWithRegexSpecialCharsIsHandledByPregQuote(): void
    {
        // Step name containing '+' is a regex quantifier that would break preg_replace without preg_quote.
        // Without preg_quote, '/^(step+name:)/m' means "one or more 'e' in 'step'" — wrong match.
        // With preg_quote, '+' is escaped to '\+' so it matches the literal '+' in the step name.
        $yaml = "version: 1.0\nevents:\n  pr-closed:\n    steps:\n      - step+name:\n          script:\n            - echo closed\n";
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $yaml);

        $this->executeCommand();

        $this->assertSame(0, $this->getStatusCode());
        $raw = (string) file_get_contents(Path::join($this->projectDir, '.gitlab-ci.yml'));
        // The TODO comment must appear immediately before the job entry.
        $this->assertMatchesRegularExpression('/# TODO: GitLab has no native pipeline trigger.*\nstep\+name:/s', $raw);
    }

    public function testYamlIndentationIsTwoSpacesAndRulesAreBlockStyle(): void
    {
        $content = (string) file_get_contents(Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'));
        file_put_contents(Path::join($this->projectDir, 'acquia-pipelines.yml'), $content);

        $this->executeCommand();

        $raw = (string) file_get_contents(Path::join($this->projectDir, '.gitlab-ci.yml'));
        // Two-space indentation: nested keys like "stage:" must be indented exactly 2 spaces.
        $this->assertMatchesRegularExpression('/^  stage:/m', $raw);
        // Must NOT use 1-space or 3-space indentation at the first level.
        $this->assertDoesNotMatchRegularExpression('/^ stage:/m', $raw);
        $this->assertDoesNotMatchRegularExpression('/^   stage:/m', $raw);
        // Rules must be block style (depth >= 4) — not inlined as { if: ..., when: ... }.
        $this->assertDoesNotMatchRegularExpression('/\{ if:/', $raw);
        $this->assertStringContainsString('if:', $raw);
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
