# Release process

1. Draft a new release and tag via the Github UI.
2. Copy and paste relevant commits since the previous release into the release notes (find relevant commits by drafting a pull request, e.g. https://github.com/acquia/cli/compare/v1.0.0-rc4...master)
3. Publish the release
4. Ensure that Travis CI builds and attaches acli.phar as a release asset.
5. Create a corresponding fix version in JIRA, assign to any tickets in “Ready to Release”, close tickets.
