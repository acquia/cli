<?php

function snakeToCamel($val) {
  preg_match('#^_*#', $val, $underscores);
  $underscores = current($underscores);
  $camel = str_replace(' ', '', ucwords(str_replace('_', ' ', $val)));
  $camel = strtolower(substr($camel, 0, 1)) . substr($camel, 1);

  return $underscores . $camel;
}

function convert($str) {
  $name = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
  $snake_regexps = [
        "#->($name)#i",
        '#\$(' . $name . ')#i',
        "#function ($name)#i",
    ];
  foreach ($snake_regexps as $regexp)
        $str = preg_replace_callback($regexp, function ($matches) {
            //print_r($matches);
            $camel = snakeToCamel($matches[1]);
            return str_replace($matches[1], $camel, $matches[0]);
        }, $str);
  return $str;

}

$path = $argv[1];
$Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
foreach ($Iterator as $file) {
  if (substr($file, -4) !== '.php')
        continue;
  echo($file);
  $out = convert(file_get_contents($file));
  file_put_contents($file, $out);
}
