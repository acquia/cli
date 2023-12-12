<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Env\EnvCertCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;

class EnvCertCreateCommandTest extends CommandTestBase {

  protected function createCommand(): CommandBase {
    return $this->injectCommand(EnvCertCreateCommand::class);
  }

  public function testCreateCert(): void {
    $applicationsResponse = $this->mockApplicationsRequest();
    $this->mockApplicationRequest();
    $environmentsResponse = $this->mockEnvironmentsRequest($applicationsResponse);
    $localMachineHelper = $this->mockLocalMachineHelper();
    $certContents = 'cert-contents';
    $keyContents = 'key-contents';
    $certName = 'cert.pem';
    $keyName = 'key.pem';
    $label = 'My certificate';
    $csrId = 123;
    $localMachineHelper->readFile($certName)->willReturn($certContents)->shouldBeCalled();
    $localMachineHelper->readFile($keyName)->willReturn($keyContents)->shouldBeCalled();

    $sslResponse = $this->getMockResponseFromSpec('/environments/{environmentId}/ssl/certificates',
      'post', '202');
    $options = [
      'json' => [
        'ca_certificates' => NULL,
        'certificate' => $certContents,
        'csr_id' => $csrId,
        'label' => $label,
        'legacy' => FALSE,
        'private_key' => $keyContents,
        ],
    ];
    $this->clientProphecy->request('post', "/environments/{$environmentsResponse->{'_embedded'}->items[0]->id}/ssl/certificates", $options)
      ->willReturn($sslResponse->{'Site is being imported'}->value)
      ->shouldBeCalled();
    $this->mockNotificationResponseFromObject($sslResponse->{'Site is being imported'}->value);

    $this->executeCommand(
      [
        '--csr-id' => $csrId,
        '--label' => $label,
        '--legacy' => FALSE,
        'certificate' => $certName,
        'private-key' => $keyName,
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
