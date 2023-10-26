<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppVcsInfo;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\App\AppVcsInfo $command
 */
class AppVcsInfoTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(AppVcsInfo::class);
  }

  /**
   * Test when no environment available for the app.
   */
  public function testNoEnvAvailableCommand(): void {
    $applications = $this->mockRequest('getApplications');
    /** @var \AcquiaCloudApi\Response\ApplicationResponse $application */
    $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
    $this->clientProphecy->request('get',
      "/applications/$application->uuid/environments")
      ->willReturn([])
      ->shouldBeCalled();
    $this->mockRequest('getCodeByApplicationUuid', $application->uuid);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('There are no environments available with this application.');

    $this->executeCommand(
      [
        'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      ],
    );
  }

  /**
   * Test when no branch or tag available for the app.
   */
  public function testNoVcsAvailableCommand(): void {
    $applications = $this->mockRequest('getApplications');
    $this->mockRequest('getApplicationByUuid', $applications[0]->uuid);
    $this->mockRequest('getApplicationEnvironments', $applications[0]->uuid);

    $this->clientProphecy->request('get',
      "/applications/{$applications[0]->uuid}/code")
      ->willReturn([])
      ->shouldBeCalled();

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('No branch or tag is available with this application.');
    $this->executeCommand(
      [
        'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      ],
    );
  }

  /**
   * Test the list of the VCS details of the application.
   */
  public function testShowVcsListCommand(): void {
    $applications = $this->mockRequest('getApplications');
    /** @var \AcquiaCloudApi\Response\ApplicationResponse $application */
    $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
    $this->mockRequest('getApplicationEnvironments', $application->uuid);
    $this->mockRequest('getCodeByApplicationUuid', $application->uuid);

    $this->executeCommand(
      [
        'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
      ],
    );

    $output = $this->getDisplay();
    $expected = <<<EOD
+-- Status of Branches and Tags of the Application ---+
| Branch / Tag Name | Deployed | Deployed Environment |
+-------------------+----------+----------------------+
| master            | Yes      | Dev                  |
| tags/01-01-2015   | Yes      | Production           |
| feature-branch    | No       | None                 |
| tags/2014-09-03   | No       | None                 |
| tags/2014-09-03.0 | No       | None                 |
+-------------------+----------+----------------------+

EOD;
    self::assertStringContainsStringIgnoringLineEndings($expected, $output);
  }

  /**
   * Test the list of deployed VCS but no deployed VCS available.
   */
  public function testNoDeployedVcs(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $response = $this->getMockEnvironmentsResponse();
    foreach ($response->_embedded->items as $key => $item) {
      // Empty the VCS
      $item->vcs = new \stdClass();
      $response->_embedded->items[$key] = $item;
    }

    $this->clientProphecy->request('get',
      "/applications/{$applicationsResponse->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn($response->_embedded->items)
      ->shouldBeCalled();
    $this->mockRequest('getCodeByApplicationUuid', $applicationsResponse->{'_embedded'}->items[0]->uuid);

    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('No branch or tag is deployed on any of the environment of this application.');
    $this->executeCommand(
      [
        'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        '--deployed',
      ],
    );
  }

  /**
   * Test the list of the only deployed VCS.
   */
  public function testListOnlyDeployedVcs(): void {
    $applications = $this->mockRequest('getApplications');
    /** @var \AcquiaCloudApi\Response\ApplicationResponse $application */
    $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
    $this->mockRequest('getApplicationEnvironments', $application->uuid);
    $this->mockRequest('getCodeByApplicationUuid', $application->uuid);

    $this->executeCommand(
      [
        'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        '--deployed',
      ],
    );

    $output = $this->getDisplay();
    $expected = <<<EOD
+-- Status of Branches and Tags of the Application ---+
| Branch / Tag Name | Deployed | Deployed Environment |
+-------------------+----------+----------------------+
| master            | Yes      | Dev                  |
| tags/01-01-2015   | Yes      | Production           |
+-------------------+----------+----------------------+

EOD;
    self::assertStringContainsStringIgnoringLineEndings($expected, $output);
  }

}
