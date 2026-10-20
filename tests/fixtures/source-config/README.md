# Source configuration fixture

`document.yml` is the configuration document a Source site returns, and
`expected/` is that same document written out as `.acquia/config` files.
Both were produced by the site itself (Drupal core's YAML encoder and
`FileStorage`), not by Acquia CLI, so the tests prove byte-for-byte fidelity
in both directions.

To regenerate from a haas-drupal DDEV checkout: a fresh `standard` install,
Dutch added, and two `language.nl` overrides so that `language/nl/` is
exercised. The expected tree is written by core's `FileStorage` so that the
layout is never reimplemented here.

```shell
cd ~/Work/haas-drupal
ddev si                                    # fresh install, standard recipe
ddev drush language:add nl -y
ddev drush php:eval '$o = \Drupal::service("language.config_factory_override"); $o->getOverride("nl", "system.site")->set("name", "Mijn site")->set("slogan", "Een slogan")->save(); $o->getOverride("nl", "node.type.article")->set("name", "Artikel")->set("description", "Een artikel")->save();'
ddev exec 'vendor/bin/dr source:config:dump --single-yaml' | tee ~/Work/cli/tests/fixtures/source-config/document.yml | ddev exec 'cat > /tmp/doc.yml'
ddev exec 'cat > /tmp/write-expected.php' <<'PHP'
<?php
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
exec('rm -rf /tmp/expected');
$default = new FileStorage('/tmp/expected');
foreach (Yaml::decode(file_get_contents('/tmp/doc.yml')) as $collection => $objects) {
  $storage = $default->createCollection($collection);
  foreach ($objects as $name => $data) {
    $storage->write($name, $data);
  }
}
PHP
ddev drush php:script /tmp/write-expected.php
cd ~/Work/cli/tests/fixtures/source-config && rm -rf expected && mkdir expected
(cd ~/Work/haas-drupal && ddev exec 'cd /tmp/expected && find . -name .htaccess -delete && tar -cf - .') | tar -xf - -C expected
```
