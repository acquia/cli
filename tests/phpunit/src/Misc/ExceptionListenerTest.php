<?php

namespace Acquia\Cli\Tests\Misc;

use Acquia\Cli\EventListener\ExceptionListener;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class ExceptionListenerTest extends TestCase {

  public function providerTestExceptionListener(): array {
    return [
      [
        new IdentityProviderException('invalid_client', 0, 0),
        'Your Cloud Platform API credentials are invalid. Run acli auth:login to reset them.'
      ],
      [
        new ApiErrorException(self::getMockResponseBody("There are no available Cloud IDEs for this application.\n")),
        "There are no available Cloud IDEs for this application.\nDelete an existing IDE (acli ide:delete) or contact your Account Manager or Acquia Sales to purchase additional IDEs.\n You may also submit a support ticket to ask for more information (https://insight.acquia.com/support/tickets/new?product=p:ride)"
      ],
      [
        new ApiErrorException(self::getMockResponseBody('Cloud API error')),
        'Cloud Platform API returned an error: Cloud API error'
      ]
    ];
  }

  /**
   * @dataProvider providerTestExceptionListener()
   *
   * @param $error
   * @param $expectedMessage
   */
  public function testExceptionListener(Throwable $error, string $expectedMessage): void {
    $exceptionListener = new ExceptionListener;
    $event = new ConsoleErrorEvent(new ArrayInput([]), new BufferedOutput(), $error);
    $exceptionListener->onConsoleError($event);
    $this->assertEquals($expectedMessage, $event->getError()->getMessage());
  }

  private static function getMockResponseBody($message) {
    return (object) [
      'message' => $message,
      'error' => ''
    ];
  }

}
