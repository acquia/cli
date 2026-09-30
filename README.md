![Build status](https://github.com/acquia/cli/actions/workflows/ci.yml/badge.svg?branch=main) [![codecov](https://codecov.io/github/acquia/cli/branch/main/graph/badge.svg?token=93Y86CBRSE)](https://codecov.io/github/acquia/cli) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Facquia%2Fcli%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/acquia/cli/main)
# Acquia CLI

The official command-line tool for interacting with the Acquia Cloud Platform and services. Acquia CLI (acli) helps you run [Drush](http://www.drush.org/) commands and tail logs from your Acquia-hosted applications, manage [Acquia Cloud IDEs](https://docs.acquia.com/acquia-cloud-platform/add-ons/cloud-ide/overview), create and manage teams and applications via the [Cloud Platform API](https://cloudapi-docs.acquia.com/), and much more!

Acquia CLI is not a local development environment. If you are looking for an integrated development environment, consider [Acquia Cloud IDE](https://docs.acquia.com/acquia-cloud-platform/add-ons/cloud-ide/overview) or third-party tools such as [Lando](https://lando.dev/).


## Installation and usage

### Quick start

Go from nothing to a working local development environment with one command:

```shell
curl -fsSL https://raw.githubusercontent.com/acquia/cli/main/install.sh | sh
```

This installs the latest Acquia CLI (a self-contained binary on macOS Apple Silicon and Linux x86_64 — no PHP required) and starts `acli dev:init`, which authenticates you with the Cloud Platform, clones one of your applications, provisions a local [ddev](https://ddev.com) stack, and imports your database and files. Already have acli installed? Just run `acli dev:init`. See `acli help dev:init` for details, including non-interactive usage for CI.

Install instructions and official documentation are available at https://docs.acquia.com/acquia-cli/install/

### Shell completion

Acquia CLI supports tab completion for bash, zsh, and fish. To enable it, run
`acli completion --help` and follow the installation instructions for your
shell. For example, for bash:

```shell
acli completion bash > /etc/bash_completion.d/acli
```

## Contribution

See [CONTRIBUTING.md](CONTRIBUTING.md) for instructions on building, testing, and contributing to Acquia CLI.

## Support

- To receive support from Acquia, visit the [Acquia Support Portal](https://acquia.my.site.com/s/).
- To receive support from and discuss ideas with other Acquia CLI users, visit the [discussions section](https://github.com/acquia/cli/discussions) on GitHub.
