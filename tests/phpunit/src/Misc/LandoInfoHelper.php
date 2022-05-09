<?php

namespace Acquia\Cli\Tests\Misc;

/**
 * Class LandoInfoHelper
 */
class LandoInfoHelper {

  public static function setLandoInfo($lando_info) {
    putenv('LANDO_INFO=' . json_encode($lando_info));
    putenv('LANDO=ON');
  }

  public static function unsetLandoInfo() {
    putenv('LANDO_INFO');
    putenv('LANDO');
  }

  public static function getLandoInfo(): object {
    return (object) [
      'appserver' =>
        (object) [
          'service' => 'appserver',
          'urls' =>
            [
              0 => 'http://my-new-app.lndo.site:8000/',
              1 => 'https://my-new-app.lndo.site/',
            ],
          'type' => 'php',
          'healthy' => TRUE,
          'via' => 'apache',
          'webroot' => 'docroot',
          'config' =>
            (object) [
              'php' => '/Users/matthew.grasmick/.lando/config/drupal9/php.ini',
            ],
          'version' => '7.4',
          'meUser' => 'www-data',
          'hasCerts' => TRUE,
          'hostnames' =>
            [
              0 => 'appserver.mynewapp.internal',
            ],
        ],
      'database' =>
        (object) [
          'service' => 'database',
          'urls' =>
            [],
          'type' => 'mysql',
          'healthy' => TRUE,
          'internal_connection' =>
            (object) [
              'host' => 'database',
              'port' => '3306',
            ],
          'external_connection' =>
            (object) [
              'host' => '127.0.0.1',
              'port' => TRUE,
            ],
          'healthcheck' => 'bash -c "[ -f /bitnami/mysql/.mysql_initialized ]"',
          'creds' =>
            (object) [
              'database' => 'drupal',
              'password' => 'drupal',
              'user' => 'drupal',
            ],
          'config' =>
            (object) [
              'database' => '/Users/matthew.grasmick/.lando/config/drupal9/mysql.cnf',
            ],
          'version' => '5.7',
          'meUser' => 'www-data',
          'hasCerts' => FALSE,
          'hostnames' =>
            [
              0 => 'database.mynewapp.internal',
            ],
        ],
    ];
  }

}
