## Box configuration

box.json is largely based on the template provided here: https://github.com/humbug/box/blob/master/fixtures/build/dir012/box.json.dist

This particular configuration is necessary to support Symfony Console. Specifically:
- Must include composer files, since Symfony uses these to determine the root directory
- Must force autodisovery and disable requirements checks, for good (but unknown) reasons
- Must force include of config and var directories

See also: https://github.com/humbug/box/blob/master/doc/symfony.md
