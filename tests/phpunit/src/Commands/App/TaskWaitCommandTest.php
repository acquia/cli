<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\TaskWaitCommand;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * @property \Acquia\Cli\Command\App\TaskWaitCommand $command
 */
class TaskWaitCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
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

    self::assertStringContainsString(' [ERROR] The task with notification uuid 1bd3487e-71d1-4fca-a2d9-5f969b3d35c1 failed', $this->getDisplay());
    $this->assertEquals(Command::FAILURE, $this->getStatusCode());
  }

  /**
   * Valid notifications.
   *
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
      [
        '"1bd3487e-71d1-4fca-a2d9-5f969b3d35c1"',
      ],
    ];
  }

  public function testTaskWaitCommandWithEmptyJson(): void {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Notification format is not one of UUID, JSON response, or URL');
    $this->executeCommand(['notification-uuid' => '{}']);

    // Assert.
  }

  public function testTaskWaitCommandWithInvalidUrl(): void {
    $this->expectException(AcquiaCliException::class);
    $this->expectExceptionMessage('Notification format is not one of UUID, JSON response, or URL');
    $this->executeCommand(['notification-uuid' => 'https://cloud.acquia.com/api/notifications/foo']);

    // Assert.
  }

  /**
   * @dataProvider providerTestTaskWaitCommandWithInvalidJson
   */
  public function testTaskWaitCommandWithInvalidJson(string $notification): void {
    $this->expectException(AcquiaCliException::class);
    $this->executeCommand([
      'notification-uuid' => $notification,
    ]);
  }

  /**
   * @return string[]
   */
  public function providerTestTaskWaitCommandWithInvalidJson(): array {
    return [
      [
        <<<'EOT'
{
  "message": "Caches are being cleared.",
  "_links": {
    "self": {
      "href": "https://cloud.acquia.com/api/environments/12-d314739e-296f-11e9-b210-d663bd873d93/domains/example.com/actions/clear-caches",
      "invalid": {
        "too-deep": "5"
      }
    },
    "notification": {
      "href": "https://cloud.acquia.com/api/notifications/1bd3487e-71d1-4fca-a2d9-5f969b3d35c1"
    }
  }
}
EOT,
      ],
      [
        <<<'EOT'
{
  "message": "Caches are being cleared.",
  "_links": {
    "self": {
      "href": "https://cloud.acquia.com/api/environments/12-d314739e-296f-11e9-b210-d663bd873d93/domains/example.com/actions/clear-caches"
    }
  }
}
EOT,
      ],
      [
        '"11bd3487e-71d1-4fca-a2d9-5f969b3d35c1"',
      ],
    ];
  }

}
