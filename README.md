[![Build Status](https://travis-ci.com/acquia/cli.svg?token=eFBAT6vQ9cqDh1Sed5Mw&branch=master)](https://travis-ci.com/acquia/cli) [![Coverage Status](https://coveralls.io/repos/github/acquia/cli/badge.svg?t=0iJBxN)](https://coveralls.io/github/acquia/cli)
# Acquia CLI

The official command-line tool for interacting with Acquia hosting and services. Acquia CLI helps you run Drush commands and tail logs from your Acquia-hosted applications, manage Acquia Remote IDEs, create and manage applications on Acquia Cloud, and much more!

Acquia CLI also helps manage your local applications by transferring files and database backups via Cloud API. However, Acquia CLI does not directly set up or manage your local development environment. If you are looking for a packaged development environment, consider [Acquia Remote IDEs](https://docs.acquia.com/dev-studio/ide/) or third-party tools such as [Lando](https://lando.dev/). 

## Installation

Acquia CLI requires PHP 7.3+ with the [PHP-JSON extension](https://www.php.net/manual/en/book.json.php) enabled. It fully supports Windows, Linux, and macOS, and will probably work on any other platform running PHP. 

Download the latest `acli.phar` file from the [releases](https://github.com/acquia/cli/releases) page, place it somewhere globally accessible on your machine, make it executable, and optionally rename it to `acli`. Or run the following simple script in the directory where you'd like to install Acquia CLI:
```console
curl -OL https://github.com/acquia/cli/releases/latest/download/acli.phar
mv acli.phar acli
chmod +x acli
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

# Development / contribution

Contributions to Acquia CLI are welcome subject to the [contributions policy](CONTRIBUTING.md).

No special tools or dependencies are necessary to develop or contrib to Acquia CLI. Simply clone the Git repo and install Composer dependencies:
```
git clone git@github.com:acquia/cli.git
cd cli
composer install
./bin/acli
```

Be sure to validate and test your code locally using the provided Composer test scripts (`composer test`) before opening a PR.
