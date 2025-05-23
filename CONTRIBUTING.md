# Contributing

## Building and testing

No special tools or dependencies are necessary to develop or contrib to Acquia CLI. Simply clone the Git repo and install Composer dependencies:
```
git clone git@github.com:acquia/cli.git
cd cli
composer install
./bin/acli
```

Be sure to validate and test your code locally using the provided Composer test scripts (`composer test`) before opening a PR.

### Building acli.phar

To test changes in production mode, build and run `acli.phar` using this process. The _build-release_ stage of [`.github/workflows/ci.yml`](.github/workflows/ci.yml) follows a similar process.

1. Install Composer production dependencies: `composer install --no-dev --optimize-autoloader`
2. Populate `.env`: `cp .env.example .env`
3. Clear and rebuild your Symfony caches: `./bin/acli ckc && ./bin/acli cc`
4. Install Box (only need to do this once): `composer box-install`
5. Compile phar: `composer box-compile`

### Writing tests

New code should be covered at 100% (or as close to it as reasonably possible) by PHPUnit tests. It should also minimize the number of escaped mutants (as close to 0% as reasonably possible), which will appear as annotations on your PR after unit tests run.

Every class / command has a corresponding test file. The first test case in each file should be the "default" passing workflow for that command. Additional test cases should cover any possible inputs for that command as well as any possible error cases.

PHPUnit data providers may be used to fuzz input for a test case as long as the output remains the same. However, if the output of a command varies non-trivially based on input, it should probably be broken into different test cases rather than using a data provider.

Test cases are declarative specifications. They should not implement or utilize any logic, especially not as provided by the covered source code itself.

## Submitting pull requests

Pull requests must also adhere to the following guidelines:

- Pull requests must be atomic and targeted at a single issue rather than broad scope.
- Pull requests must contain clear testing steps and justification, and all other information required by the pull request template.
- Pull requests must pass automated tests before they will be reviewed. Acquia recommends running the tests locally before submitting.
- Pull requests must meet Drupal coding standards and best practices as defined by the project maintainers.

### Automatic dev builds

Every commit on the Acquia CLI repository, including for pull requests, automatically builds and uploads acli.phar as a build artifact to assist with reviews. To download acli.phar for any commit:

1. For pull requests, GitHub Actions will comment on the PR with a link to the dev build.
2. For any other commit, wait for the CI workflow to complete.
3. On the workflow summary page, in the "Artifacts" section, click on `acli.phar`.
4. Unzip the downloaded file.
5. Make the file executable: `chmod +x acli.phar`

## Updating Cloud Platform API spec

Acquia CLI stores a local copy of the Cloud Platform API spec in the `assets` directory. To update the Cloud Platform API spec, run:

```
composer update-cloud-api-spec
```

## Releasing

1. The release drafter plugin should have already created a release for you, [visible in the GitHub UI](https://github.com/acquia/cli/releases), according to [Semantic Versioning](https://semver.org/). ![image](https://user-images.githubusercontent.com/539205/134036494-c7000fb0-94e6-4594-a09f-bb1601745d5a.png)
2. Ensure that release notes are accurate and issues are correctly labeled.
3. Ensure that release has been approved by maintainers and any other required stakeholders.
4. Validate that testing has passed on the commit to be released.
5. In the GitHub UI, publish the release. This will trigger a [GitHub actions build](https://github.com/acquia/cli/blob/731cb747060e06940b2b5e6994df1bcc86325a7a/.github/workflows/ci.yml#L47-L69) that generates a phar file and attaches it to the release. ![image](https://user-images.githubusercontent.com/539205/134036674-4dd6db98-5fe4-413c-abe3-3a6f35b0fc31.png)


### If the build fails...

If the build fails to generate a phar and attach it properly, follow these steps to manually create and attach the phar.

1. Check out the tag locally
```
git remote update
git checkout [the tag]
```
1. Follow the steps above for [Building acli.phar](#building-acliphar)
2. Validate that the phar works and has the right version defined:
```
./build/acli.phar --version
```
3. Attach the phar file to the release in the GitHub UI.

## Generating docs

To generate docs for all commands in RST format, run:
```
./bin/acli self:make-docs
```

To copy the output easily to the clipboard, run:
```
./bin/acli self:make-docs | pbcopy
```

If you're on Mac, you can render and view the outputted RST using a command like this:
```
brew install restview
./bin/acli self:make-docs > /tmp/acli.rst && restview /tmp/acli.rst
```

## Style guide

Code, comment, and other style standards should generally follow those set by the PHP community and upstream projects, especially [Drupal](https://www.drupal.org/docs/develop/standards), [Symfony](https://symfony.com/doc/current/contributing/code/standards.html), [ORCA](https://github.com/acquia/coding-standards-php), and [PSR-1](https://www.php-fig.org/psr/psr-1/). PHPCodeSniffer enforces many of these standards.

- Organize commands by topic (noun) first and action (verb) second, separated by a colon (`ide:create`).
- Write command descriptions in sentence case and imperative mood without a trailing period (`Create a Cloud IDE`). Do not use a trailing period for argument and option descriptions.
- Use camelCase for all property, method, and variable names.
- Use hyphens to separate words in options and arguments (`addOption('ssh-key')`), or any other variable exposed to end users.
