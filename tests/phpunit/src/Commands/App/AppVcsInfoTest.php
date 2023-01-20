<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\AppVcsInfo;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class AppVcsInfoTest.
 *
 * @property \Acquia\Cli\Command\App\AppVcsInfo $command
 */
class AppVcsInfoTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(AppVcsInfo::class);
  }

  /**
   * Test when no environment available for the app.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   * @throws \Exception
   */
  public function testNoEnvAvailableCommand(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/environments")
      ->willReturn([])
      ->shouldBeCalled();
    $this->mockApplicationCodeRequest($applications_response);

    try {
      $this->executeCommand(
        [
          'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        ],
      );
    }
    catch (AcquiaCliException $e) {
      self::assertEquals('There are no environments available with this application.', $e->getMessage());
    }
  }

  /**
   * Test when no branch or tag available for the app.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testNoVcsAvailableCommand(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);

    $this->clientProphecy->request('get',
      "/applications/{$applications_response->{'_embedded'}->items[0]->uuid}/code")
      ->willReturn([])
      ->shouldBeCalled();

    try {
      $this->executeCommand(
        [
          'applicationUuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
        ],
      );
    }
    catch (AcquiaCliException $e) {
      self::assertEquals('No branch or tag is available with this application.', $e->getMessage());
    }
  }

  /**
   * Test the list of the VCS details of the application.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   */
  public function testShowVcsListCommand(): void {
    $applications_response = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $this->mockEnvironmentsRequest($applications_response);
    $this->mockApplicationCodeRequest($applications_response);

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
|                   | Yes      | Stage                |
| feature-branch    | No       | None                 |
| tags/2014-09-03   | No       | None                 |
| tags/2014-09-03.0 | No       | None                 |
+-------------------+----------+----------------------+

EOD;
    self::assertStringContainsStringIgnoringLineEndings($expected, $output);
  }

}
