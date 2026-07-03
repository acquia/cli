# Acquia CLI

PHP 8.2+ Symfony Console application distributed as a PHAR (built with Box).
Commands talk to the Acquia Cloud Platform API and Acquia Cloud Site Factory
(ACSF) API.

## Commands

```shell
composer install          # install dependencies
composer test             # lint + cs + stan + unit
composer unit             # full PHPUnit suite (serial group, then paratest)
composer cs               # phpcs (acquia/coding-standards)
composer cbf              # phpcbf autofix
composer stan             # PHPStan (pass --memory-limit=2G if it OOMs)
composer mutation         # Infection mutation testing
composer box-install && composer box-compile  # build var/acli.phar
```

Run a single test class:

```shell
vendor/bin/phpunit --filter SomeCommandTest tests/phpunit/src/Commands/...
```

GrumPHP runs phpcs as a pre-commit hook; commits fail on style violations.

## Architecture

- `bin/acli` boots `src/Kernel.php` (Symfony DI container, services wired in
  `config/`). Commands are services in `src/Command/`.
- `src/Command/CommandBase.php` is the base for all commands: telemetry,
  authentication, alias→UUID conversion, update checks.
- `api:*` and `acsf:*` commands are NOT hand-written: they are generated at
  runtime by `src/Command/Api/ApiCommandHelper.php` from the OpenAPI specs in
  `assets/acquia-spec.json` and `assets/acsf-spec.yaml`. To change an API
  command, change the helper or regenerate the spec (`composer
  update-cloud-api-spec`), not individual command classes.
- `src/DataStore/` persists config: `CloudDataStore` (`~/.acquia/cloud_api.conf`,
  contains API credentials — must stay chmod 0600) and `AcquiaCliDatastore`.
- Telemetry goes to Amplitude and errors to Bugsnag via
  `src/Helpers/TelemetryHelper.php`. Anything derived from command input must
  pass through `TelemetryHelper::redactSensitiveData()` before being sent.

## Conventions

- TDD: write or update the failing PHPUnit test before changing `src/`.
- Tests use prophecy mocks; base classes `tests/phpunit/src/TestBase.php` and
  `CommandTestBase.php` provide mock helpers for the Cloud API client
  (`mockRequest()` reads from the OpenAPI spec fixtures).
- PHPUnit metadata uses PHP attributes (`#[Group]`, `#[DataProvider]`), not
  doc-comment annotations.
- Tests that mutate global state belong in the `serial` group
  (`#[Group('serial')]`); everything else runs under paratest.
- `declare(strict_types=1);` in every file; strict comparisons; phpcs enforces
  ordered use-statement imports.
- Process execution: always pass argument arrays to
  `LocalMachineHelper::execute()`; never interpolate user input into
  `executeFromCmd()` shell strings.
- SSH/rsync/git invocations use `-o StrictHostKeyChecking=accept-new`; do not
  weaken to `=no`.

## Gotchas

- Symfony is pinned to 6.4: `typhonius/acquia-logstream` blocks Symfony 7.
- PHP_CodeSniffer is pinned to 3.x: `acquia/coding-standards` and
  `drupal/coder` block phpcs 4.
- `minimum-stability: dev` is required by the `consolidation/self-update`
  fork pin; `prefer-stable: true` keeps everything else on stable releases.
- The update check in `CommandBase::checkForNewVersion()` is cached for 24h
  (`self:clear-caches` clears it).
