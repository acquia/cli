## Box configuration

box.json is largely based on the template provided here: https://github.com/humbug/box/blob/master/fixtures/build/dir012/box.json.dist

This particular configuration is necessary to support Symfony Console. Specifically:
- Must include composer files, since Symfony uses these to determine the root directory
- Must force autodisovery, since Symfony won't be able to find service classes otherwise
- Must force include of config and var directories, since Symfony uses these for cache and config

See also: https://github.com/humbug/box/blob/master/doc/symfony.md
