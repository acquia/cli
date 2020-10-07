# Release process

1. Draft a new release and tag via the Github UI.
1. Copy and paste relevant commits since the previous release into the release notes (find relevant commits by drafting a pull request, e.g. https://github.com/acquia/cli/compare/v1.0.0...master)
1. Pay special attention to issues with the "change record" label, these need to be called out in release notes.
1. Publish the release.
1. Ensure that Travis CI builds and attaches acli.phar as a release asset.
1. Update RELEASE.md and README.md with new version numbers.
