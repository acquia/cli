# How to release

0. The release drafter plugin should have already created a release for you, [visible in the GitHub UI](https://github.com/acquia/cli/releases). ![image](https://user-images.githubusercontent.com/539205/134036494-c7000fb0-94e6-4594-a09f-bb1601745d5a.png)
2. Rename the release if necessary to accurately follow [Semantic Versioning](https://semver.org/).
3. Ensure that release notes are accurate and issues are correctly labeled.
4. Ensure that release has been approved by maintainers and any other required stakeholders.
5. Validate that testing has passed on the commit to be released.
6. In the GitHub UI, publish the release. This will trigger a [GitHub actions build](https://github.com/acquia/cli/blob/731cb747060e06940b2b5e6994df1bcc86325a7a/.github/workflows/ci.yml#L47-L69) that generates a phar file and attaches it to the release. ![image](https://user-images.githubusercontent.com/539205/134036674-4dd6db98-5fe4-413c-abe3-3a6f35b0fc31.png)


## If the build fails...

If the build fails to generate a phar and attach it properly, follow these steps to manually create and attach the phar.

1. Run these commands, which essentially reproduce the [commands that occur in the build](https://github.com/acquia/cli/blob/731cb747060e06940b2b5e6994df1bcc86325a7a/.github/workflows/ci.yml#L57-L63).
```
git remote update
git checkout [the tag]
composer install --no-dev --optimize-autoloader
composer box-install
# Generate .env.local.php
composer dump-env prod
# Warm the symfony cache so it gets bundled with phar.
./bin/acli
composer box-compile
```
2. Validate that the phar works and has the right version defined:
```
./build/acli.phar --version
```
3. Attach the phar file to the release in the GitHub UI.
