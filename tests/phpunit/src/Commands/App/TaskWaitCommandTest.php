<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\TaskWaitCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\App\TaskWaitCommand $command
 */
class TaskWaitCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(TaskWaitCommand::class);
  }

  /**
   * @dataProvider providerTestTaskWaitCommand
   */
  public function testTaskWaitCommand(string $notification): void {
    $notificationUuid = '1bd3487e-71d1-4fca-a2d9-5f969b3d35c1';
    $this->mockRequest('getNotificationByUuid', $notificationUuid);
    $this->executeCommand([
      'notification-uuid' => $notification,
    ]);
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    self::assertStringContainsString(' [OK] The task with notification uuid 1bd3487e-71d1-4fca-a2d9-5f969b3d35c1 completed', $output);
    $this->assertStringContainsString('Progress: 100', $output);
    $this->assertStringContainsString('Completed: Mon Jul 29 20:47:13 UTC 2019', $output);
    $this->assertStringContainsString('Task type: Application added to recents list', $output);
    $this->assertStringContainsString('Duration: 0 seconds', $output);
    $this->assertEquals(Command::SUCCESS, $this->getStatusCode());
  }

  public function testTaskWaitCommandWithFailedTask(): void {
    $notificationUuid = '1bd3487e-71d1-4fca-a2d9-5f969b3d35c1';
    $this->mockRequest(
      'getNotificationByUuid',
      $notificationUuid,
      NULL,
      NULL,
      function ($response): void {
        $response->status = 'failed';}
    );
    $this->executeCommand([
      'notification-uuid' => $notificationUuid,
    ]);
    $this->prophet->checkPredictions();
    self::assertStringContainsString(' [ERROR] The task with notification uuid 1bd3487e-71d1-4fca-a2d9-5f969b3d35c1 failed', $this->getDisplay());
    $this->assertEquals(Command::FAILURE, $this->getStatusCode());
  }

  /**
   * @return (string|int)[][]
   */
  public function providerTestTaskWaitCommand(): array {
    return [
      [
        '1bd3487e-71d1-4fca-a2d9-5f969b3d35c1',
      ],
      [
        'https://cloud.acquia.com/api/notifications/1bd3487e-71d1-4fca-a2d9-5f969b3d35c1',
      ],
      [
        <<<'EOT'
{
  "message": "Caches are being cleared.",
  "_links": {
    "self": {
      "href": "https://cloud.acquia.com/api/environments/12-d314739e-296f-11e9-b210-d663bd873d93/domains/example.com/actions/clear-caches"
    },
    "notification": {
      "href": "https://cloud.acquia.com/api/notifications/1bd3487e-71d1-4fca-a2d9-5f969b3d35c1"
    }
  }
}
EOT,
      ],
    ];
  }

  public function testTaskWaitCommandWithInvalidInput(): void {
    $this->expectException(AcquiaCliException::class);
    $this->executeCommand(['notification-uuid' => '{}']);

    // Assert.
    $this->prophet->checkPredictions();
  }

}
