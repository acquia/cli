# Acquia CLI — Agent & Contributor Guide

This file is the primary entry point for AI agents and automated tooling working on this repository. It covers how to claim work, run in parallel without colliding, and execute the full contribution workflow.

For **build and test commands, architecture, and conventions**, see [CLAUDE.md](CLAUDE.md).
For **PR guidelines, style guide, and release process**, see [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Overview

Acquia CLI (`acli`) is a PHP 8.2+ Symfony Console application distributed as a PHAR. Commands talk to the Acquia Cloud Platform API and ACSF API. The test suite is fully local (no live API calls in unit tests — Prophecy mocks read from OpenAPI spec fixtures).

---

## Claiming work

1. Find an open, unassigned issue in the [GitHub issue tracker](https://github.com/acquia/cli/issues).
2. Self-assign it, or leave a comment like "I'm working on this" to signal intent.
3. One issue per branch/worktree — do not combine unrelated changes.
4. Work in a branch named after the issue: `feature/CLI-123-short-description` or `fix/CLI-123-short-description`.

---

## Worktree-per-feature pattern

When multiple agents or contributors work in parallel, use a git worktree so each has an isolated working tree with no shared index state.

```bash
# Create a worktree for your branch (from the repo root)
git worktree add .worktrees/CLI-123-my-feature -b CLI-123-my-feature

# Work inside the worktree
cd .worktrees/CLI-123-my-feature
composer install   # worktree has its own vendor/ symlink

# When done, clean up
cd ../..
git worktree remove .worktrees/CLI-123-my-feature
```

`.worktrees/` is git-ignored — no accidental commits of worktree state.

---

## Agent workflow

Follow this sequence for every change:

1. **Claim** — assign yourself to a GitHub issue (see above).
2. **Create worktree** — `git worktree add .worktrees/<branch> -b <branch>`.
3. **Write the failing test first** — per the TDD convention in `CLAUDE.md`:
   ```bash
   vendor/bin/phpunit --filter MyNewCommandTest tests/phpunit/src/Commands/...
   # Confirm it fails before touching src/
   ```
4. **Implement** — make the test pass; do not touch unrelated files.
5. **Run the full gate** — `composer test` must exit 0:
   ```bash
   composer test   # lint + cs + stan + unit (serial + parallel)
   ```
6. **Open a PR** — title format: `CLI-123: Short imperative description`. Link the issue in the PR body (`Fixes #123`). Follow the PR template in [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Pre-commit hook scope

GrumPHP runs automatically on `git commit` and executes:

- **phpcs** — PHP CodeSniffer style check
- **phpstan** — static analysis (level 3, baseline-suppressed pre-existing violations)

**GrumPHP does NOT run PHPUnit.** A passing commit hook does not mean the test suite passes.

> You MUST run `composer test` and confirm it exits 0 before pushing.

---

## PHPStan baseline

`phpstan-baseline.neon` suppresses 237 pre-existing violations that existed when the level was raised from 1 to 3. The baseline is intentional — it is NOT a workaround to use when your new code introduces a violation.

- **Do not** regenerate the baseline to silence a new violation.
- **Do** fix new violations before committing.
- When an existing violation is fixed, re-run `vendor/bin/phpstan --generate-baseline phpstan-baseline.neon --memory-limit=1G` to shrink the baseline.

---

## Parallel work rules

- One open PR per issue. If your work depends on another in-progress PR, branch from it and note the dependency in your PR description.
- Never edit the same generated API command class as another open PR — `api:*` and `acsf:*` commands are generated from `assets/acquia-spec.json`; conflicts here are hard to resolve. Coordinate via the issue tracker.
- The `serial` PHPUnit group (`#[Group('serial')]`) contains tests that mutate global state. They run sequentially; do not parallelise them.
