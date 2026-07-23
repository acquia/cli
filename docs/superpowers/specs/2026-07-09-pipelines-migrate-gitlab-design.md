# Design: `pipelines:migrate:gitlab` Command

**Date:** 2026-07-09
**Branch:** PIPE-8085
**Author:** Shubham Bansal

## Overview

A new standalone CLI command that converts an Acquia `acquia-pipelines.yml` (or `.yaml`) file into a generic `.gitlab-ci.yml` (or `.gitlab-ci.yaml`) file. Fully offline — no Acquia Cloud API calls, no GitLab authentication, no network required. The source file is not deleted after conversion.

This is distinct from the existing `codestudio:pipelines-migrate` command, which converts to a Code Studio (Acquia's GitLab-based product) specific `.gitlab-ci.yml` with AutoDevOps template inclusion and requires GitLab + Cloud Platform auth.

---

## Command Interface

**Name:** `pipelines:migrate:gitlab`
**Alias:** `p:m:g`
**Location:** `src/Command/Pipelines/PipelinesMigrateGitlabCommand.php`

**Options:**
- `--path` (optional, `VALUE_REQUIRED`) — path to the **directory** containing the acquia-pipelines file. The option itself is optional; if omitted, defaults to `$projectDir` (the CWD where acli is invoked, injected via Symfony DI). If provided, a value is required.

**Usage examples:**
```
acli pipelines:migrate:gitlab
acli pipelines:migrate:gitlab --path=/path/to/repo
acli p:m:g
```

---

## File Detection

1. Resolve target directory: use `--path` if provided, otherwise `$projectDir`.
2. If `--path` is provided and the directory does not exist → throw `AcquiaCliException`: `"The path '<dir>' does not exist."`
3. Look for `acquia-pipelines.yml` first, then `acquia-pipelines.yaml`.
4. If neither exists → throw `AcquiaCliException`: `"No acquia-pipelines.yml or acquia-pipelines.yaml file found in <dir>."`
5. Output filename mirrors input extension:
   - `acquia-pipelines.yml` → `.gitlab-ci.yml`
   - `acquia-pipelines.yaml` → `.gitlab-ci.yaml`
6. Output is written to the **same directory** as the input file.
7. If the output file already exists → overwrite it and emit `io->warning("Existing <output-file> was overwritten.")`.

---

## YAML Conversion Mapping

### `services` block

| Acquia service entry | GitLab CI output |
|---|---|
| `php: version: X` | Top-level `image: php:X` |
| `mysql` or `mysql: version: X` | Top-level `services: [mysql]` or `services: [mysql:X]` |
| `composer: version: X` | `before_script: [composer install]` added to every job in the `build` stage |
| Any other service | Skipped with `io->warning()` — user must configure manually |

### `variables.global`

Flattened to top-level `variables:` (the `global:` wrapper is stripped). Same approach as `CodeStudioPipelinesMigrateCommand::migrateVariablesSection()`.

### `stages` declaration

Only stages with actual content are included. Order is always:
```yaml
stages:
  - build
  - fail-on-build
  - post-deploy
  - pr-merged
  - pr-closed
```

### Event → Stage mapping

| Acquia event | GitLab stage | Special GitLab rule |
|---|---|---|
| `build` | `build` | None (runs on every pipeline) |
| `fail-on-build` | `fail-on-build` | `when: on_failure` on every job in this stage |
| `post-deploy` | `post-deploy` | None (runs always, after build) |
| `pr-merged` | `pr-merged` | `rules: [{if: '$CI_PIPELINE_SOURCE == "merge_request_event"', when: on_success}]` + YAML comment: `# TODO: Adjust rule — GitLab has no direct "merged" pipeline event. Consider using push pipelines on your default branch instead.` |
| `pr-closed` | `pr-closed` | `rules: [{if: '$CI_PIPELINE_SOURCE == "merge_request_event"', when: manual}]` + YAML comment: `# TODO: GitLab has no native pipeline trigger for a closed-without-merge MR. This is a best-effort placeholder — review and adjust manually.` |

### Step → Job mapping

Each step within an event becomes a named GitLab CI job:
```yaml
<step-name>:
  stage: <mapped-stage>
  script:
    - <command1>
    - <command2>
  # + when: on_failure   (fail-on-build steps only)
  # + rules: [...]       (pr-merged / pr-closed steps only)
  # + before_script: [composer install]  (build steps only, if composer service present)
```

---

## Error Handling & Console Output

### Errors — throw `AcquiaCliException`, halt execution

- `--path` directory does not exist
- Input file not found in target directory
- Input file is empty or invalid YAML
- Input file has no `events` key

### Warnings — `io->warning()`, continue

- An event exists in the file but has no steps → skip that stage, warn user
- A step exists but has no `script` key or empty script → skip that job, warn user
- A `services` entry is not `php`, `mysql`, or `composer` → skip it, warn user to configure manually

### Success messages — `io->success()`

- `"Migrated 'variables' section."` (if variables present)
- `"Migrated '<event>' event."` (one per successfully migrated event)
- Final: `"Migration complete. Created <output-file>. Review the file before committing — some manual adjustments may be needed."`

### Source file behavior

The source `acquia-pipelines.yml`/`.yaml` file is **NOT deleted** after conversion. The user decides what to do with it.

---

## Implementation Structure

```
src/Command/Pipelines/
  PipelinesMigrateGitlabCommand.php   # the command class

tests/phpunit/src/Commands/Pipelines/
  PipelinesMigrateGitlabCommandTest.php

tests/fixtures/
  acquia-pipelines.yml                # already exists, reused
  acquia-pipelines.yaml               # new fixture (tests .yaml extension mirroring)
```

No new trait. No shared code with `CodeStudioPipelinesMigrateCommand`. All conversion logic is private methods on the command class.

The command is auto-discovered by Symfony DI via the existing `resource: ../../src/Command` glob in `config/prod/services.yml` — no manual registration needed.

---

## Test Cases

Test file: `tests/phpunit/src/Commands/Pipelines/PipelinesMigrateGitlabCommandTest.php`

Uses `#[DataProvider]`, `#[Group]` PHP attributes. Extends `CommandTestBase`. No API client mocking needed.

| # | Scenario | Assertion |
|---|---|---|
| 1 | Full conversion — all 5 events, services, variables | Output file exists; stages, jobs, variables, image, services all correct |
| 2 | `--path` flag — custom directory | File found and output written to correct directory |
| 3 | `.yaml` extension input | Output file is `.gitlab-ci.yaml` not `.gitlab-ci.yml` |
| 4 | Missing input file | `AcquiaCliException` thrown |
| 5 | Missing `events` key | `AcquiaCliException` thrown |
| 6 | Step with empty `script` | Job skipped, no exception, warning emitted |
| 7 | Unknown service entry | Warning emitted, rest of conversion proceeds |
| 8 | `fail-on-build` steps | Each job has `when: on_failure` |
| 9 | `pr-merged` / `pr-closed` jobs | Correct `rules:` on each job |
| 10 | Source file not deleted | `acquia-pipelines.yml` still present after command runs |
| 11 | Output file already exists | Overwritten, warning emitted |
| 12 | `--path` points to non-existent directory | `AcquiaCliException` thrown |
