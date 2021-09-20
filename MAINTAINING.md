# How to release

0. The release drafter plugin should have already created a release for you, visible in the GitHub UI.
1. Rename the release if necessary to accurately using SemVer rules.
2. In the GitHub UI, ensure that release notes are accurate and issues are correctly labeled.
3. Ensure that release has been approved by maintainers and any other required stakeholders.
4. Validate that testing has passed.
5. Publish the release. This will trigger a GitHub actions build that generates a phar file and attaches it to the release.

## If the build fails...

If the build fails to generate a phar and attach it properly, follow these steps to manually create and attach the phar.

1. Run these commands.
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