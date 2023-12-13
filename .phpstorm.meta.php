<?php
namespace PHPSTORM_META {

  use AcquiaCloudApi\Response\ApplicationResponse;
  use AcquiaCloudApi\Response\ApplicationsResponse;
  use AcquiaCloudApi\Response\DatabasesResponse;
  use AcquiaCloudApi\Response\EnvironmentsResponse;

  override(\Acquia\Cli\Tests\TestBase::mockRequest(), map([
    'getApplications' => ApplicationsResponse::class,
    'getApplicationByUuid' => ApplicationResponse::class,
    'getApplicationEnvironments' => EnvironmentsResponse::class,
    'getEnvironmentsDatabases' => DatabasesResponse::class
  ]));

}
