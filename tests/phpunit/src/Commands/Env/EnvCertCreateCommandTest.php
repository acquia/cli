<?php

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\Env\EnvCertCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

class EnvCertCreateCommandTest extends CommandTestBase {

  protected function createCommand(): Command {
    return $this->injectCommand(EnvCertCreateCommand::class);
  }

  public function testCreateCert() {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $certContents = 'cert-contents';
    $keyContents = 'key-contents';
    $localMachineHelper->readFile('cert.pem')->willReturn($certContents)->shouldBeCalled();
    $localMachineHelper->readFile('key.pem')->willReturn($keyContents)->shouldBeCalled();
    $this->command->localMachineHelper = $localMachineHelper->reveal();
    $sslResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/ssl/certificates',
      'post', '202');
    $sslResponseValue = $sslResponse->{'Site is being imported'}->value;
    $options = [
      'json' => [
        'ca_certificates' => NULL,
        'certificate' => $certContents,
        'csr_id' => 0,
        'label' => 'My certificate',
        'legacy' => FALSE,
        'private_key' => $keyContents,
        ],
    ];
    $this->clientProphecy->request('post', "/environments/{$environmentsResponse->{'_embedded'}->items[0]->id}/ssl/certificates", $options)
      ->willReturn($sslResponseValue)
      ->shouldBeCalled();
    $notificationUuid = substr($sslResponseValue->_links->notification->href, -36);
    $this->mockNotificationResponse($notificationUuid);

    $this->executeCommand(
      [
        'certificate' => 'cert.pem',
        'private-key' => 'key.pem',
      ],
      [
        // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'
        'n',
        // Select a Cloud Platform application: [Sample application 1]:
        0,
      ]
    );
    $this->prophet->checkPredictions();
  }

}
