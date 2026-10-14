# pipelines:migrate:gitlab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a standalone `pipelines:migrate:gitlab` CLI command that converts an `acquia-pipelines.yml`/`.yaml` file into a generic `.gitlab-ci.yml`/`.gitlab-ci.yaml` file with no API calls or authentication required.

**Architecture:** A single new command class `PipelinesMigrateGitlabCommand` in `src/Command/Pipelines/` that extends `CommandBase`. All conversion logic lives as private methods on the class. Auto-discovered by Symfony DI — no `services.yml` changes needed. Tests use the existing `CommandTestBase` / `injectCommand()` pattern with a real `Filesystem` (no API mocking).

**Tech Stack:** PHP 8.2+, Symfony Console, `symfony/yaml`, `symfony/filesystem`, PHPUnit 10+, Prophecy.

## Global Constraints

- `declare(strict_types=1);` in every file.
- PHPUnit metadata uses PHP attributes (`#[Group]`, `#[DataProvider]`), not doc-comment annotations.
- Tests that mutate global state belong in `#[Group('serial')]`; everything else can run under paratest.
- Strict comparisons (`===`) throughout.
- `use` statements must be in alphabetical order (phpcs enforces this).
- No comments unless the WHY is non-obvious.
- Source `acquia-pipelines.yml`/`.yaml` file must NOT be deleted after conversion.
- Command name: `pipelines:migrate:gitlab`, alias: `p:m:g`.
- Output file extension mirrors input: `.yml` → `.yml`, `.yaml` → `.yaml`.

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php` | The command class |
| Create | `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php` | All 12 test cases |
| Create | `tests/fixtures/acquia-pipelines-full.yml` | Fixture with all 5 events + services + variables |
| Modify | `tests/fixtures/acquia-pipelines.yml` | Reused as-is (already has build + post-deploy) |

---

## Task 1: Test fixture with full acquia-pipelines content

**Files:**
- Create: `tests/fixtures/acquia-pipelines-full.yml`

**Interfaces:**
- Produces: A fixture file used by Tasks 2 and 3. Contains all 5 events (`build`, `fail-on-build`, `post-deploy`, `pr-merged`, `pr-closed`), a `services` block with `php`, `mysql`, and `composer`, and a `variables.global` block.

- [ ] **Step 1: Create the fixture file**

```yaml
# tests/fixtures/acquia-pipelines-full.yml
version: 1.3.0
services:
  - php:
      version: '8.3'
  - composer:
      version: 2
  - mysql

variables:
  global:
    SIMPLETEST_BASE_URL: "http://127.0.0.1:8080"
    SIMPLETEST_DB: "mysql://root:root@localhost/drupal"

events:
  build:
    steps:
      - setup:
          type: script
          script:
            - composer validate --no-check-all --ansi
            - mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS drupal"
      - validate:
          type: script
          script:
            - composer drupal:validate
  fail-on-build:
    steps:
      - notify:
          type: script
          script:
            - echo "Build failed"
            - curl -X POST https://hooks.example.com/notify
  post-deploy:
    steps:
      - deploy:
          type: script
          script:
            - echo "Deploying"
            - pipelines-deploy
  pr-merged:
    steps:
      - cleanup:
          type: script
          script:
            - echo "PR merged"
            - pipelines-deploy
  pr-closed:
    steps:
      - teardown:
          type: script
          script:
            - echo "PR closed"
            - pipelines-deploy
```

- [ ] **Step 2: Verify the fixture parses cleanly**

```bash
cd /path/to/repo && php -r "echo json_encode(\Symfony\Component\Yaml\Yaml::parseFile('tests/fixtures/acquia-pipelines-full.yml'), JSON_PRETTY_PRINT);"
```
Expected: Valid JSON output with all 5 events present.

---

## Task 2: Command skeleton + failing test (TDD gate)

**Files:**
- Create: `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php`
- Create: `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php`

**Interfaces:**
- Consumes: `tests/fixtures/acquia-pipelines-full.yml` from Task 1; `injectCommand()` from `TestBase`; `CommandBase` from `src/Command/CommandBase.php`
- Produces: `PipelinesMigrateGitlabCommand` class with `#[AsCommand]` attribute, `configure()` with `--path` option, and a stub `execute()` that returns `Command::SUCCESS`. Test class with `createCommand()` and one smoke test.

- [ ] **Step 1: Write the failing smoke test**

Note: `testFullConversionAllEvents` is defined here as a minimal smoke test. It will be replaced with a full assertion version in Task 5 — remove this version when adding the Task 5 version.

Create `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php`:

```php
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
}
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
vendor/bin/phpunit --filter testFullConversionAllEvents tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: FAIL — class `PipelinesMigrateGitlabCommand` not found.

- [ ] **Step 3: Create the command skeleton**

Create `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pipelines:migrate:gitlab', description: 'Convert an acquia-pipelines.yml file to a generic .gitlab-ci.yml file', aliases: ['p:m:g'])]
final class PipelinesMigrateGitlabCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the directory containing the acquia-pipelines.yml file. Defaults to the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the smoke test — confirm it passes**

```bash
vendor/bin/phpunit --filter testFullConversionAllEvents tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: FAIL — `assertFileExists` fails because no file is written yet. That is the correct TDD state. The class is found now.

- [ ] **Step 5: Commit the skeleton**

```bash
git add src/Command/Pipelines/PipelinesMigrateGitlabCommand.php \
        tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php \
        tests/fixtures/acquia-pipelines-full.yml
git commit -m "feat: scaffold PipelinesMigrateGitlabCommand with failing test"
```

---

## Task 3: File detection logic

**Files:**
- Modify: `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php`
- Modify: `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php`

**Interfaces:**
- Consumes: `$input->getOption('path')`, `$this->projectDir` (injected by DI), `Symfony\Component\Filesystem\Path`, `AcquiaCliException`
- Produces: Private method `resolveSourceFile(InputInterface $input): array` returning `['path' => string, 'extension' => string]`. Throws `AcquiaCliException` on missing directory or missing source file.

- [ ] **Step 1: Write failing tests for file detection edge cases**

Add these test methods to `PipelinesMigrateGitlabCommandTest`:

```php
use Acquia\Cli\Exception\AcquiaCliException;

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
```

- [ ] **Step 2: Run the new tests — confirm they fail**

```bash
vendor/bin/phpunit --filter "testMissingInputFileThrows|testNonExistentPathOptionThrows|testYamlExtensionInputProducesYamlOutput|testPathOption" tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All FAIL.

- [ ] **Step 3: Implement file detection in the command**

Replace the `execute()` method and add the private helper. Full updated file:

```php
<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'pipelines:migrate:gitlab', description: 'Convert an acquia-pipelines.yml file to a generic .gitlab-ci.yml file', aliases: ['p:m:g'])]
final class PipelinesMigrateGitlabCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the directory containing the acquia-pipelines.yml file. Defaults to the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceFile = $this->resolveSourceFile($input);
        $acquiaPipelinesContents = $this->parseSourceFile($sourceFile['path']);
        $gitlabCiContents = $this->convert($acquiaPipelinesContents);
        $outputPath = Path::join(dirname($sourceFile['path']), '.gitlab-ci.' . $sourceFile['extension']);

        if ($this->localMachineHelper->getFilesystem()->exists($outputPath)) {
            $this->io->warning("Existing $outputPath was overwritten.");
        }

        $this->localMachineHelper->getFilesystem()->dumpFile(
            $outputPath,
            Yaml::dump($gitlabCiContents, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->io->success("Migration complete. Created $outputPath. Review the file before committing — some manual adjustments may be needed.");

        return Command::SUCCESS;
    }

    /**
     * @return array{path: string, extension: string}
     */
    private function resolveSourceFile(InputInterface $input): array
    {
        $dir = $input->getOption('path') ?? $this->projectDir;

        if (!$this->localMachineHelper->getFilesystem()->exists($dir)) {
            throw new AcquiaCliException("The path '{$dir}' does not exist.");
        }

        foreach (['yml', 'yaml'] as $extension) {
            $candidate = Path::join($dir, "acquia-pipelines.$extension");
            if ($this->localMachineHelper->getFilesystem()->exists($candidate)) {
                return ['path' => $candidate, 'extension' => $extension];
            }
        }

        throw new AcquiaCliException("No acquia-pipelines.yml or acquia-pipelines.yaml file found in {$dir}.");
    }

    /**
     * @return array<mixed>
     */
    private function parseSourceFile(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new AcquiaCliException("The file {$path} is empty or unreadable.");
        }
        try {
            $parsed = Yaml::parse($raw);
        } catch (ParseException $e) {
            throw new AcquiaCliException("Failed to parse {$path}: " . $e->getMessage());
        }
        if (!is_array($parsed) || !array_key_exists('events', $parsed)) {
            throw new AcquiaCliException("The file {$path} does not contain an 'events' key.");
        }
        return $parsed;
    }

    /**
     * @param array<mixed> $acquiaPipelinesContents
     * @return array<mixed>
     */
    private function convert(array $acquiaPipelinesContents): array
    {
        // Stub — full implementation in Task 4.
        return ['stages' => ['build']];
    }
}
```

- [ ] **Step 4: Run the file-detection tests — confirm they pass**

```bash
vendor/bin/phpunit --filter "testMissingInputFileThrows|testNonExistentPathOptionThrows|testYamlExtensionInputProducesYamlOutput|testPathOption" tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Command/Pipelines/PipelinesMigrateGitlabCommand.php \
        tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
git commit -m "feat: add file detection logic to PipelinesMigrateGitlabCommand"
```

---

## Task 4: Services and variables conversion

**Files:**
- Modify: `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php`
- Modify: `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php`

**Interfaces:**
- Consumes: Parsed `$acquiaPipelinesContents` array from `parseSourceFile()`
- Produces: Private methods `migrateServices(array $contents): array` and `migrateVariables(array $contents): array`, both returning partial GitLab CI arrays to be merged into the final output.

- [ ] **Step 1: Write failing tests for services and variables**

Add to `PipelinesMigrateGitlabCommandTest`:

```php
#[Group('serial')]
public function testServicesMapping(): void
{
    $fs = new Filesystem();
    $fs->copy(
        Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
        Path::join($this->projectDir, 'acquia-pipelines.yml')
    );

    $this->executeCommand();

    $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
    $this->assertSame('php:8.3', $contents['image']);
    $this->assertContains('mysql', $contents['services']);
}

#[Group('serial')]
public function testVariablesMapping(): void
{
    $fs = new Filesystem();
    $fs->copy(
        Path::join($this->realFixtureDir, 'acquia-pipelines-full.yml'),
        Path::join($this->projectDir, 'acquia-pipelines.yml')
    );

    $this->executeCommand();

    $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));
    $this->assertArrayHasKey('variables', $contents);
    $this->assertArrayHasKey('SIMPLETEST_BASE_URL', $contents['variables']);
    $this->assertArrayHasKey('SIMPLETEST_DB', $contents['variables']);
    // The 'global' wrapper must be stripped.
    $this->assertArrayNotHasKey('global', $contents['variables']);
}

#[Group('serial')]
public function testUnknownServiceEmitsWarning(): void
{
    $fs = new Filesystem();
    $customContent = "version: 1.0\nservices:\n  - redis\nevents:\n  build:\n    steps:\n      - step1:\n          script:\n            - echo hi\n";
    $fs->dumpFile(Path::join($this->projectDir, 'acquia-pipelines.yml'), $customContent);

    $this->executeCommand();

    $this->assertSame(0, $this->getStatusCode());
    $this->assertStringContainsString('redis', $this->getDisplay());
}
```

- [ ] **Step 2: Run the new tests — confirm they fail**

```bash
vendor/bin/phpunit --filter "testServicesMapping|testVariablesMapping|testUnknownServiceEmitsWarning" tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All FAIL.

- [ ] **Step 3: Implement services and variables conversion**

Replace the `convert()` stub and add the two new private methods. Add these private methods to the class and update `convert()`:

```php
/**
 * @param array<mixed> $acquiaPipelinesContents
 * @return array<mixed>
 */
private function convert(array $acquiaPipelinesContents): array
{
    $gitlabCi = [];

    $servicesOutput = $this->migrateServices($acquiaPipelinesContents);
    if (isset($servicesOutput['image'])) {
        $gitlabCi['image'] = $servicesOutput['image'];
    }
    if (isset($servicesOutput['services'])) {
        $gitlabCi['services'] = $servicesOutput['services'];
    }

    $variables = $this->migrateVariables($acquiaPipelinesContents);
    if (!empty($variables)) {
        $gitlabCi['variables'] = $variables;
    }

    // stages and jobs added in Task 5
    $gitlabCi['stages'] = ['build'];

    return $gitlabCi;
}

/**
 * @param array<mixed> $contents
 * @return array<mixed>
 */
private function migrateServices(array $contents): array
{
    $output = [];
    $composerFound = false;

    if (!array_key_exists('services', $contents)) {
        return $output;
    }

    foreach ($contents['services'] as $service) {
        if (is_string($service)) {
            $name = $service;
            $version = null;
        } else {
            $name = array_key_first($service);
            $version = $service[$name]['version'] ?? null;
        }

        match ($name) {
            'php' => $output['image'] = 'php:' . $version,
            'mysql' => $output['services'][] = $version ? "mysql:$version" : 'mysql',
            'composer' => $composerFound = true,
            default => $this->io->warning("Service '$name' is not supported and was skipped. Configure it manually in the generated .gitlab-ci file."),
        };
    }

    // Store composer flag for use in job generation (Task 5).
    if ($composerFound) {
        $output['_composer'] = true;
    }

    return $output;
}

/**
 * @param array<mixed> $contents
 * @return array<mixed>
 */
private function migrateVariables(array $contents): array
{
    if (!array_key_exists('variables', $contents)) {
        return [];
    }

    $vars = $contents['variables'];

    // Strip the 'global' wrapper if present.
    if (array_key_exists('global', $vars) && is_array($vars['global'])) {
        return $vars['global'];
    }

    return $vars;
}
```

- [ ] **Step 4: Run the services/variables tests — confirm they pass**

```bash
vendor/bin/phpunit --filter "testServicesMapping|testVariablesMapping|testUnknownServiceEmitsWarning" tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Command/Pipelines/PipelinesMigrateGitlabCommand.php \
        tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
git commit -m "feat: implement services and variables migration"
```

---

## Task 5: Events conversion (stages + jobs)

**Files:**
- Modify: `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php`
- Modify: `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php`

**Interfaces:**
- Consumes: `$acquiaPipelinesContents['events']`, `$servicesOutput['_composer']` from `migrateServices()`
- Produces: Private method `migrateEvents(array $contents, bool $hasComposer): array` returning the full set of stage names and job definitions for the GitLab CI file. The `convert()` method is updated to call this and merge the results.

- [ ] **Step 1: Write failing tests for events conversion**

Add to `PipelinesMigrateGitlabCommandTest`:

```php
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
    $contents = Yaml::parseFile(Path::join($this->projectDir, '.gitlab-ci.yml'));

    // Stages.
    $this->assertSame(
        ['build', 'fail-on-build', 'post-deploy', 'pr-merged', 'pr-closed'],
        $contents['stages']
    );

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

    // pr-closed jobs have rules.
    $this->assertArrayHasKey('teardown', $contents);
    $this->assertSame('pr-closed', $contents['teardown']['stage']);
    $this->assertArrayHasKey('rules', $contents['teardown']);

    // Source file not deleted.
    $this->assertFileExists(Path::join($this->projectDir, 'acquia-pipelines.yml'));
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
    $this->expectExceptionMessage("does not contain an 'events' key");
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
```

- [ ] **Step 2: Run the new tests — confirm they fail**

```bash
vendor/bin/phpunit --filter "testFullConversionAllEvents|testFailOnBuildJobsHaveWhenOnFailure|testPrMergedAndPrClosedRules|testStepWithEmptyScriptIsSkipped|testMissingEventsKeyThrows|testSourceFileIsNotDeleted|testExistingOutputFileIsOverwritten" tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All FAIL.

- [ ] **Step 3: Implement events migration**

Replace the `convert()` method and add `migrateEvents()`. The full updated command file:

```php
<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'pipelines:migrate:gitlab', description: 'Convert an acquia-pipelines.yml file to a generic .gitlab-ci.yml file', aliases: ['p:m:g'])]
final class PipelinesMigrateGitlabCommand extends CommandBase
{
    // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
    private const EVENT_STAGE_MAP = [
        'build' => [
            'stage' => 'build',
            'when' => null,
            'rules' => null,
        ],
        'fail-on-build' => [
            'stage' => 'fail-on-build',
            'when' => 'on_failure',
            'rules' => null,
        ],
        'post-deploy' => [
            'stage' => 'post-deploy',
            'when' => null,
            'rules' => null,
        ],
        'pr-merged' => [
            'stage' => 'pr-merged',
            'when' => null,
            'rules' => [
                [
                    'if' => '$CI_PIPELINE_SOURCE == "merge_request_event"',
                    'when' => 'on_success',
                ],
            ],
        ],
        'pr-closed' => [
            'stage' => 'pr-closed',
            'when' => null,
            'rules' => [
                [
                    'if' => '$CI_PIPELINE_SOURCE == "merge_request_event"',
                    'when' => 'manual',
                ],
            ],
        ],
    ];
    // phpcs:enable

    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the directory containing the acquia-pipelines.yml file. Defaults to the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceFile = $this->resolveSourceFile($input);
        $acquiaPipelinesContents = $this->parseSourceFile($sourceFile['path']);
        $gitlabCiContents = $this->convert($acquiaPipelinesContents);
        $outputPath = Path::join(dirname($sourceFile['path']), '.gitlab-ci.' . $sourceFile['extension']);

        if ($this->localMachineHelper->getFilesystem()->exists($outputPath)) {
            $this->io->warning("Existing $outputPath was overwritten.");
        }

        $this->localMachineHelper->getFilesystem()->dumpFile(
            $outputPath,
            Yaml::dump($gitlabCiContents, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->io->success("Migration complete. Created $outputPath. Review the file before committing — some manual adjustments may be needed.");

        return Command::SUCCESS;
    }

    /**
     * @return array{path: string, extension: string}
     */
    private function resolveSourceFile(InputInterface $input): array
    {
        $dir = $input->getOption('path') ?? $this->projectDir;

        if (!$this->localMachineHelper->getFilesystem()->exists($dir)) {
            throw new AcquiaCliException("The path '{$dir}' does not exist.");
        }

        foreach (['yml', 'yaml'] as $extension) {
            $candidate = Path::join($dir, "acquia-pipelines.$extension");
            if ($this->localMachineHelper->getFilesystem()->exists($candidate)) {
                return ['path' => $candidate, 'extension' => $extension];
            }
        }

        throw new AcquiaCliException("No acquia-pipelines.yml or acquia-pipelines.yaml file found in {$dir}.");
    }

    /**
     * @return array<mixed>
     */
    private function parseSourceFile(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new AcquiaCliException("The file {$path} is empty or unreadable.");
        }
        try {
            $parsed = Yaml::parse($raw);
        } catch (ParseException $e) {
            throw new AcquiaCliException("Failed to parse {$path}: " . $e->getMessage());
        }
        if (!is_array($parsed) || !array_key_exists('events', $parsed)) {
            throw new AcquiaCliException("The file {$path} does not contain an 'events' key.");
        }
        return $parsed;
    }

    /**
     * @param array<mixed> $acquiaPipelinesContents
     * @return array<mixed>
     */
    private function convert(array $acquiaPipelinesContents): array
    {
        $gitlabCi = [];

        $servicesOutput = $this->migrateServices($acquiaPipelinesContents);
        if (isset($servicesOutput['image'])) {
            $gitlabCi['image'] = $servicesOutput['image'];
        }
        if (isset($servicesOutput['services'])) {
            $gitlabCi['services'] = $servicesOutput['services'];
        }

        $variables = $this->migrateVariables($acquiaPipelinesContents);
        if (!empty($variables)) {
            $gitlabCi['variables'] = $variables;
        }

        $hasComposer = isset($servicesOutput['_composer']) && $servicesOutput['_composer'];
        $eventsOutput = $this->migrateEvents($acquiaPipelinesContents, $hasComposer);

        $gitlabCi['stages'] = $eventsOutput['stages'];
        unset($eventsOutput['stages']);

        return array_merge($gitlabCi, $eventsOutput);
    }

    /**
     * @param array<mixed> $contents
     * @return array<mixed>
     */
    private function migrateServices(array $contents): array
    {
        $output = [];
        $composerFound = false;

        if (!array_key_exists('services', $contents)) {
            return $output;
        }

        foreach ($contents['services'] as $service) {
            if (is_string($service)) {
                $name = $service;
                $version = null;
            } else {
                $name = array_key_first($service);
                $version = $service[$name]['version'] ?? null;
            }

            match ($name) {
                'php' => $output['image'] = 'php:' . $version,
                'mysql' => $output['services'][] = $version ? "mysql:$version" : 'mysql',
                'composer' => $composerFound = true,
                default => $this->io->warning("Service '$name' is not supported and was skipped. Configure it manually in the generated .gitlab-ci file."),
            };
        }

        if ($composerFound) {
            $output['_composer'] = true;
        }

        return $output;
    }

    /**
     * @param array<mixed> $contents
     * @return array<mixed>
     */
    private function migrateVariables(array $contents): array
    {
        if (!array_key_exists('variables', $contents)) {
            return [];
        }

        $vars = $contents['variables'];

        if (array_key_exists('global', $vars) && is_array($vars['global'])) {
            return $vars['global'];
        }

        return $vars;
    }

    /**
     * @param array<mixed> $contents
     * @return array<mixed>
     */
    private function migrateEvents(array $contents, bool $hasComposer): array
    {
        $stages = [];
        $jobs = [];

        foreach (self::EVENT_STAGE_MAP as $eventName => $eventConfig) {
            if (!array_key_exists($eventName, $contents['events'])) {
                continue;
            }

            $eventData = $contents['events'][$eventName];
            if (empty($eventData['steps'])) {
                $this->io->warning("Event '$eventName' has no steps and was skipped.");
                continue;
            }

            $stages[] = $eventConfig['stage'];

            foreach ($eventData['steps'] as $step) {
                $stepName = array_key_first($step);
                $stepData = $step[$stepName];

                if (empty($stepData['script'])) {
                    $this->io->warning("Step '$stepName' in event '$eventName' has no script and was skipped.");
                    continue;
                }

                $job = ['stage' => $eventConfig['stage']];

                if ($hasComposer && $eventName === 'build') {
                    $job['before_script'] = ['composer install'];
                }

                $job['script'] = $stepData['script'];

                if ($eventConfig['when'] !== null) {
                    $job['when'] = $eventConfig['when'];
                }

                if ($eventConfig['rules'] !== null) {
                    $job['rules'] = $eventConfig['rules'];
                }

                $jobs[$stepName] = $job;
            }

            $this->io->success("Migrated '$eventName' event.");
        }

        return array_merge(['stages' => $stages], $jobs);
    }
}
```

- [ ] **Step 4: Run all events tests — confirm they pass**

```bash
vendor/bin/phpunit --filter "testFullConversionAllEvents|testFailOnBuildJobsHaveWhenOnFailure|testPrMergedAndPrClosedRules|testStepWithEmptyScriptIsSkipped|testMissingEventsKeyThrows|testSourceFileIsNotDeleted|testExistingOutputFileIsOverwritten" tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Command/Pipelines/PipelinesMigrateGitlabCommand.php \
        tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
git commit -m "feat: implement events migration with stages and job rules"
```

---

## Task 6: Full test suite run and cleanup

**Files:**
- Modify: `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php` (remove any duplicate test method if `testFullConversionAllEvents` was defined twice during TDD)

**Interfaces:**
- Consumes: All prior tasks
- Produces: Green test suite for the new command; passing `composer test` output.

- [ ] **Step 1: Run the full test class**

```bash
vendor/bin/phpunit tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: All 12 tests PASS with no errors or warnings.

- [ ] **Step 2: Run phpcs on the new files**

```bash
composer cs -- src/Command/Pipelines/PipelinesMigrateGitlabCommand.php \
               tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php
```
Expected: No violations. If there are `use` ordering issues, run `composer cbf` on those files.

- [ ] **Step 3: Run PHPStan**

```bash
composer stan -- --memory-limit=2G
```
Expected: No errors. Fix any type issues (e.g. missing `@param`/`@return` docblocks PHPStan infers, `array_key_first` on non-empty array).

- [ ] **Step 4: Run the full test suite**

```bash
composer test
```
Expected: All tests pass.

- [ ] **Step 5: Final commit**

```bash
git add src/Command/Pipelines/PipelinesMigrateGitlabCommand.php \
        tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php \
        tests/fixtures/acquia-pipelines-full.yml
git commit -m "feat: complete pipelines:migrate:gitlab command with full test coverage"
```
