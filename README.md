[![Build Status](https://travis-ci.com/acquia/cli.svg?token=eFBAT6vQ9cqDh1Sed5Mw&branch=master)](https://travis-ci.com/acquia/cli) [![Coverage Status](https://coveralls.io/repos/github/acquia/cli/badge.svg?t=0iJBxN&service=github)](https://coveralls.io/github/acquia/cli)
# Acquia CLI

The official command-line tool for interacting with Acquia hosting and services. Acquia CLI helps you run [Drush](http://www.drush.org/) commands and tail logs from your Acquia-hosted applications, manage [Acquia Cloud IDEs](https://docs.acquia.com/dev-studio/ide/), create and manage teams and applications via the [Acquia Cloud API](https://cloudapi-docs.acquia.com/), and much more!

Acquia CLI does not provide or manage local development environments. If you are looking for a packaged development environment, consider [Acquia Cloud IDE](https://docs.acquia.com/dev-studio/ide/) or third-party tools such as [Lando](https://lando.dev/). 

## Installation

Acquia CLI requires PHP 7.3+ with the [PHP-JSON extension](https://www.php.net/manual/en/book.json.php) enabled. It fully supports Windows, Linux, and macOS, and will probably work on any other platform running PHP. 

Download the latest `acli.phar` file from the [releases](https://github.com/acquia/cli/releases) page, make it executable, and optionally rename it to `acli`. 

For example, you can run following simple script:
```bash
curl -OL https://github.com/acquia/cli/releases/download/v1.0.0-rc4/acli.phar
chmod +x acli.phar
```

Next, place it somewhere globally accessible on your machine. For instance:
```
mv acli.phar /usr/local/bin/acli
```

Acquia CLI cannot and should not be installed via Composer. It is distributed only as a [self-contained Phar archive](https://www.php.net/manual/en/phar.using.intro.php) in order to avoid dependency conflicts.

## Usage

You probably want to start by linking Acquia CLI to your Acquia Cloud account using `acli auth:login`:
```console
$ acli auth:login
You will need an Acquia Cloud API token from https://cloud.acquia.com/a/profile/tokens.
You should create a new token specifically for Developer Studio and enter the associated key and secret below.
Do you want to open this page to generate a token now?
```

Note that if you use other Acquia tools such as ADS CLI, BLT, or Pipelines CLI, your computer may already be linked and you can skip this step.

Acquia CLI commands provide inline help and docoumentation. Run `acli` or `acli list` to see a list of all available options and commands:
```console
$ acli
Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help                     Displays help for a command
  ...
```

Run `acli help [command]` to get help for a particular command:
```console
$ acli help ssh-key:create
Description:
  Create an ssh key on your local machine

Usage:
  ssh-key:create [options]

Options:
      --filename=FILENAME  The filename of the SSH key
      --password=PASSWORD  The password for the SSH key
  -h, --help               Display this help message

```

## Similar tools
Several tools compliment or duplicate Acquia CLI functionality. Acquia CLI can safely be used with any of these tools, although some functionality may be duplicated.
- [Acquia BLT](https://github.com/acquia/blt): Provides an automation framework for setting up and managing Drupal applications. Acquia BLT is installed in a Drupal application and provides support for just that application, while Acquia CLI should be installed globally and allows you to interact with any Acquia service. Acquia CLI is not an automation framework like Acquia BLT.
- [ADS CLI](https://docs.acquia.com/dev-studio/cli/): Provides local development environments based on Lando and an interface for interacting with Acquia services. Acquia CLI also provides an interface for interacting with Acquia services, but does not provide a local development environment. **Rather than ADS CLI, consider using Acquia CLI alongside either [Acquia Cloud IDE](https://docs.acquia.com/dev-studio/ide/) or third-party development environments such as [Lando](https://lando.dev/).**
- [Pipelines CLI](https://docs.acquia.com/acquia-cloud/develop/pipelines/cli/): Provides an interface for managing [Acquia Pipelines](https://docs.acquia.com/acquia-cloud/develop/pipelines) jobs. Acquia CLI does not allow you to manage Pipelines jobs, although this functionality is in the roadmap.
- [Typhonius Acquia CLI](https://github.com/typhonius/acquia_cli): Provides just an interface for Cloud API. Acquia CLI (acquia/cli) is a superset of this functionality, with access to the Cloud API as well as Acquia services not part of Cloud API.
- [ACSF tools](https://github.com/acquia/acsf-tools): Provides Drush commands for interacting with ACSF. Duplicates some functionality of Acquia CLI, but in the form of Drush commands rather than a standalone CLI.

## Development / contribution

Contributions to Acquia CLI are welcome subject to the [contributions policy](CONTRIBUTING.md), which also has more detailed information on how to develop Acquia CLI.

## Support

Until Acquia CLI has a stable 1.0.0 release support for it is limited to basic diagnostics only. Please refer to our
[Support Users Guide](https://docs.acquia.com/support/guide/) for more information on product and services
we officially support.
