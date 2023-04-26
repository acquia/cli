<?php

namespace Acquia\Cli\Tests\Misc;

class LandoInfoHelper {

  public static function setLandoInfo($lando_info): void {
    putenv('LANDO_INFO=' . json_encode($lando_info));
    putenv('LANDO=ON');
  }

  public static function unsetLandoInfo(): void {
    putenv('LANDO_INFO');
    putenv('LANDO');
  }

  public static function getLandoInfo(): object {
    return (object) [
      'appserver' => (object) [
          'config' => (object) [
              'php' => '/Users/matthew.grasmick/.lando/config/drupal9/php.ini',
            ],
          'hasCerts' => TRUE,
          'healthy' => TRUE,
          'hostnames' => [
              0 => 'appserver.mynewapp.internal',
            ],
          'meUser' => 'www-data',
          'service' => 'appserver',
          'type' => 'php',
          'urls' => [
              0 => 'http://my-new-app.lndo.site:8000/',
              1 => 'https://my-new-app.lndo.site/',
            ],
          'version' => '7.4',
          'via' => 'apache',
          'webroot' => 'docroot',
        ],
      'database' => (object) [
          'config' => (object) [
              'database' => '/Users/matthew.grasmick/.lando/config/drupal9/mysql.cnf',
            ],
          'creds' => (object) [
              'database' => 'drupal',
              'password' => 'drupal',
              'user' => 'drupal',
            ],
          'external_connection' => (object) [
              'host' => '127.0.0.1',
              'port' => TRUE,
            ],
          'hasCerts' => FALSE,
          'healthcheck' => 'bash -c "[ -f /bitnami/mysql/.mysql_initialized ]"',
          'healthy' => TRUE,
          'hostnames' => [
              0 => 'database.mynewapp.internal',
            ],
          'internal_connection' => (object) [
              'host' => 'database',
              'port' => '3306',
            ],
          'meUser' => 'www-data',
          'service' => 'database',
          'type' => 'mysql',
          'urls' => [],
          'version' => '5.7',
        ],
    ];
  }

}
