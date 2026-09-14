# Source configuration fixture

`document.yml` is the configuration document a Source site returns, and
`expected/` is that same document written out as `.acquia/config` files.
Both were produced by the site itself (Drupal core's YAML encoder), not by
Acquia CLI, so the tests prove byte-for-byte fidelity in both directions.

To regenerate from a haas-drupal DDEV checkout (fresh `standard` install,
Dutch added, with one or two `language.nl` overrides so that
`language/nl/` is exercised):

```shell
cd ~/Work/haas-drupal
ddev exec 'vendor/bin/dr source:config:dump --single-yaml > /tmp/doc.yml'
ddev exec 'cat > /tmp/write-expected.php' <<'PHP'
<?php
use Drupal\Component\Serialization\Yaml;
$document = Yaml::decode(file_get_contents('/tmp/doc.yml'));
foreach ($document as $collection => $objects) {
  $dir = '/tmp/expected' . ($collection === '' ? '' : '/' . str_replace('.', '/', $collection));
  mkdir($dir, 0777, TRUE);
  foreach ($objects as $name => $values) {
    file_put_contents("$dir/$name.yml", Yaml::encode($values));
  }
}
PHP
ddev exec 'rm -rf /tmp/expected' && ddev drush php:script /tmp/write-expected.php
cd ~/Work/cli/tests/fixtures/source-config && rm -rf expected && mkdir expected
(cd ~/Work/haas-drupal && ddev exec 'cd /tmp/expected && tar -cf - .') | tar -xf - -C expected
(cd ~/Work/haas-drupal && ddev exec 'cat /tmp/doc.yml') > document.yml
```
