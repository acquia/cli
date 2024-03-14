<?php
namespace PHPSTORM_META {

  use AcquiaCloudApi\Response\AccountResponse;
  use AcquiaCloudApi\Response\ApplicationResponse;
  use AcquiaCloudApi\Response\ApplicationsResponse;
  use AcquiaCloudApi\Response\CronResponse;
  use AcquiaCloudApi\Response\CronsResponse;
  use AcquiaCloudApi\Response\DatabasesResponse;
  use AcquiaCloudApi\Response\EnvironmentResponse;
  use AcquiaCloudApi\Response\EnvironmentsResponse;

  override(\Acquia\Cli\Tests\TestBase::mockRequest(), map([
    'getAccount' => AccountResponse::class,
    'getApplications' => ApplicationsResponse::class,
    'getApplicationByUuid' => ApplicationResponse::class,
    'getApplicationEnvironments' => EnvironmentsResponse::class,
    'getEnvironmentsDatabases' => DatabasesResponse::class,
    'getEnvironment' => EnvironmentResponse::class,
    'getCron' => CronResponse::class,
    'getCronJobsByEnvironmentId' => CronsResponse::class
  ]));

}
