<?php

namespace Acquia\Cli\Tests\Commands\App;

use Acquia\Cli\Command\App\TaskWaitCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class TaskWaitCommand.
 *
 * @property \Acquia\Cli\Command\App\TaskWaitCommand $command
 */
class TaskWaitCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(TaskWaitCommand::class);
  }

  /**
   * @throws \Exception
   * @dataProvider providerTestTaskWaitCommand
   */
  public function testTaskWaitCommand(string $status, string $message): void {
    $notification_uuid = '94835c3e-b112-4660-a14d-d541906c205b';
    $this->mockNotificationResponse($notification_uuid, $status);
    $this->executeCommand([
      'notification-uuid' => $notification_uuid,
    ], []);

    // Assert.
    $this->prophet->checkPredictions();
    $output = $this->getDisplay();
    $this->assertStringContainsString($message, $output);
    $this->assertStringContainsString('Progress: 100', $output);
    $this->assertStringContainsString('Completed: Mon Jul 29 20:47:13 UTC 2019', $output);
    $this->assertStringContainsString('Task type: Application added to recents list', $output);
    $this->assertStringContainsString('Duration: 0 seconds', $output);
  }

  public function providerTestTaskWaitCommand(): array {
    return [
      [
        'completed',
        ' [OK] The task with notification uuid 1bd3487e-71d1-4fca-a2d9-5f969b3d35c1 completed'
      ],
      [
        'failed',
        ' [ERROR] The task with notification uuid 1bd3487e-71d1-4fca-a2d9-5f969b3d35c1 failed'
      ]
    ];
  }

  /**
   * @throws \Exception|\Psr\Cache\InvalidArgumentException
   */
  public function testTaskWaitCommandWithStandardInput(): void {
    $this->mockNotificationResponse('42b56cff-0b55-4bdf-a949-1fd0fca61c6c');
    $task_response = $this->getMockResponseFromSpec('/environments/{environmentId}/domains/{domain}/actions/clear-caches', 'post', 202);
    $json = json_encode($task_response->{'Clearing cache'}->value);
    $this->executeCommand(['notification-uuid' => $json], []);

    // Assert.
    $this->prophet->checkPredictions();
  }

  /**
   * @throws \Exception
   */
  public function testTaskWaitCommandWithInvalidInput(): void {
    $this->expectException(AcquiaCliException::class);
    $this->executeCommand(['notification-uuid' => '{}'], []);

    // Assert.
    $this->prophet->checkPredictions();
  }

}
