[![Build Status](https://travis-ci.com/acquia/cli.svg?token=eFBAT6vQ9cqDh1Sed5Mw&branch=master)](https://travis-ci.com/acquia/cli) [![Coverage Status](https://coveralls.io/repos/github/acquia/cli/badge.svg?t=0iJBxN&service=github)](https://coveralls.io/github/acquia/cli)
# Acquia CLI

The official command-line tool for interacting with the Drupal Cloud Platform and services. Acquia CLI helps you run [Drush](http://www.drush.org/) commands and tail logs from your Acquia-hosted applications, manage [Acquia Cloud IDEs](https://docs.acquia.com/dev-studio/ide/), create and manage teams and applications via the [Cloud Platform API](https://cloudapi-docs.acquia.com/), and much more!

Acquia CLI does not provide or manage local development environments. If you are looking for a packaged development environment, consider [Acquia Cloud IDE](https://docs.acquia.com/dev-studio/ide/) or third-party tools such as [Lando](https://lando.dev/). 

**Install instructions and official documentation are available at https://docs.acquia.com/dev-studio/acquia-cli/install/**

## Similar tools
Several tools compliment or duplicate Acquia CLI functionality. Acquia CLI can safely be used with any of these tools, although some functionality may be duplicated.
- [Acquia BLT](https://github.com/acquia/blt): Provides an automation framework for setting up and managing Drupal applications. Acquia BLT is installed in a Drupal application and provides support for just that application, while Acquia CLI should be installed globally and allows you to interact with any Acquia service. Acquia CLI is not an automation framework like Acquia BLT.
- [Pipelines CLI](https://docs.acquia.com/acquia-cloud/develop/pipelines/cli/): Provides an interface for managing [Acquia Pipelines](https://docs.acquia.com/acquia-cloud/develop/pipelines) jobs. Acquia CLI does not allow you to manage Pipelines jobs, although this functionality is in the roadmap.
- [Typhonius Acquia CLI](https://github.com/typhonius/acquia_cli): Provides just an interface for Cloud API. Acquia CLI (acquia/cli) is a superset of this functionality, with access to the Cloud API as well as Acquia services not part of Cloud API.
- [ACSF tools](https://github.com/acquia/acsf-tools): Provides Drush commands for interacting with ACSF. Duplicates some functionality of Acquia CLI, but in the form of Drush commands rather than a standalone CLI.

# Example Usage


### Interact with Cloud API

Trying Using [jq](https://stedolan.github.io/jq/) to highlight and parse JSON output from `acli api` commands.
```
// Get a list of all Acquia Cloud Platform applications that you have access to.
$ acli api:applications:list
// Do the same, but highlight the JSON output.
$ acli api:applications:list | jq
// Output only the "name" field for every object in the JSON output.
$ acli api:applications:list | jq '.[] | .name'
// Output only the first object in the JSON output.
$ acli api:applications:list | jq '.[0]'
```

### Manage SSH keys

```
// Create a new SSH key locally and upload it to Acquia Cloud Platform.
$ ssh-key:create-upload
// List all local and remote SSH keys.
$ acli ssh-key:list
```

### Manage IDEs

```
// Create a new Acquia Cloud IDE
$ acli ide:create
// List existing IDEs.
$ acli ide:list
// Open an IDE in your web browser.
$ acli ide:open
```

### Interact with Acquia Cloud Platform Environments

```
// List aliases for all environments.
$ acli remote:aliases:list
// SSH into an environment.
$ acli ssh myapp.dev
// Run a drush command in an environment.
$ acli drush myapp.dev cache-rebuild
```

## Development / contribution

Contributions to Acquia CLI are welcome subject to the [contributions policy](CONTRIBUTING.md), which also has more detailed information on how to develop Acquia CLI.

## Support

Please refer to our [Support Users Guide](https://docs.acquia.com/support/guide/) for more information on products and services we officially support.
