<?php

// Application 'eemgrasmick', environment 'dev'.
$aliases['dev'] = [
  'root' => '/var/www/html/eemgrasmick.dev/docroot',
  'ac-site' => 'eemgrasmick',
  'ac-env' => 'dev',
  'ac-realm' => 'prod',
  'uri' => 'eemgrasmickdev.prod.acquia-sites.com',
  'path-aliases' =>
  [
    '%drush-script' => 'drush8',
  ],
  'dev.livedev' =>
  [
    'parent' => '@eemgrasmick.dev',
    'root' => '/mnt/gfs/eemgrasmick.dev/livedev/docroot',
  ],
  'remote-host' => 'eemgrasmickdev.ssh.prod.acquia-sites.com',
  'remote-user' => 'eemgrasmick.dev',
];

// Application 'eemgrasmick', environment 'ode7'.
$aliases['ode7'] = [
  'root' => '/var/www/html/eemgrasmick.ode7/docroot',
  'ac-site' => 'eemgrasmick',
  'ac-env' => 'ode7',
  'ac-realm' => 'prod',
  'uri' => 'eemgrasmickode7.prod.acquia-sites.com',
  'path-aliases' =>
  [
    '%drush-script' => 'drush8',
  ],
  'ode7.livedev' =>
  [
    'parent' => '@eemgrasmick.ode7',
    'root' => '/mnt/gfs/eemgrasmick.ode7/livedev/docroot',
  ],
  'remote-host' => 'eemgrasmickode7.ssh.prod.acquia-sites.com',
  'remote-user' => 'eemgrasmick.ode7',
];

// Application 'eemgrasmick', environment 'ode9'.
$aliases['ode9'] = [
  'root' => '/var/www/html/eemgrasmick.ode9/docroot',
  'ac-site' => 'eemgrasmick',
  'ac-env' => 'ode9',
  'ac-realm' => 'prod',
  'uri' => 'eemgrasmickode9.prod.acquia-sites.com',
  'path-aliases' =>
  [
    '%drush-script' => 'drush8',
  ],
  'ode9.livedev' =>
  [
    'parent' => '@eemgrasmick.ode9',
    'root' => '/mnt/gfs/eemgrasmick.ode9/livedev/docroot',
  ],
  'remote-host' => 'eemgrasmickode9.ssh.prod.acquia-sites.com',
  'remote-user' => 'eemgrasmick.ode9',
];

// Application 'eemgrasmick', environment 'prod'.
$aliases['prod'] = [
  'root' => '/var/www/html/eemgrasmick.prod/docroot',
  'ac-site' => 'eemgrasmick',
  'ac-env' => 'prod',
  'ac-realm' => 'prod',
  'uri' => 'eemgrasmick.prod.acquia-sites.com',
  'path-aliases' =>
  [
    '%drush-script' => 'drush8',
  ],
  'prod.livedev' =>
  [
    'parent' => '@eemgrasmick.prod',
    'root' => '/mnt/gfs/eemgrasmick.prod/livedev/docroot',
  ],
  'remote-host' => 'eemgrasmick.ssh.prod.acquia-sites.com',
  'remote-user' => 'eemgrasmick.prod',
];

// Application 'eemgrasmick', environment 'ra'.
$aliases['ra'] = [
  'root' => '/var/www/html/eemgrasmick.ra/docroot',
  'ac-site' => 'eemgrasmick',
  'ac-env' => 'ra',
  'ac-realm' => 'prod',
  'uri' => 'eemgrasmickra.prod.acquia-sites.com',
  'path-aliases' =>
  [
    '%drush-script' => 'drush8',
  ],
  'ra.livedev' =>
  [
    'parent' => '@eemgrasmick.ra',
    'root' => '/mnt/gfs/eemgrasmick.ra/livedev/docroot',
  ],
  'remote-host' => 'eemgrasmickra.ssh.prod.acquia-sites.com',
  'remote-user' => 'eemgrasmick.ra',
];

// Application 'eemgrasmick', environment 'test'.
$aliases['test'] = [
  'root' => '/var/www/html/eemgrasmick.test/docroot',
  'ac-site' => 'eemgrasmick',
  'ac-env' => 'test',
  'ac-realm' => 'prod',
  'uri' => 'eemgrasmickstg.prod.acquia-sites.com',
  'path-aliases' =>
  [
    '%drush-script' => 'drush8',
  ],
  'test.livedev' =>
  [
    'parent' => '@eemgrasmick.test',
    'root' => '/mnt/gfs/eemgrasmick.test/livedev/docroot',
  ],
  'remote-host' => 'eemgrasmickstg.ssh.prod.acquia-sites.com',
  'remote-user' => 'eemgrasmick.test',
];

