<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Env\EnvCertCreateCommand;
use Acquia\Cli\Tests\CommandTestBase;

class EnvCertCreateCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(EnvCertCreateCommand::class);
    }

    public function testCreateCert(): void
    {
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $environments = $this->mockRequest('getApplicationEnvironments', $application->uuid);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $certContents = 'cert-contents';
        $keyContents = 'key-contents';
        $certName = 'cert.pem';
        $keyName = 'key.pem';
        $label = 'My certificate';
        $csrId = 123;
        $localMachineHelper->readFile($certName)
            ->willReturn($certContents)
            ->shouldBeCalled();
        $localMachineHelper->readFile($keyName)
            ->willReturn($keyContents)
            ->shouldBeCalled();

        $sslResponse = $this->getMockResponseFromSpec(
            '/environments/{environmentId}/ssl/certificates',
            'post',
            '202'
        );
        $options = [
            'json' => [
                'ca_certificates' => null,
                'certificate' => $certContents,
                'csr_id' => $csrId,
                'label' => $label,
                'legacy' => false,
                'private_key' => $keyContents,
            ],
        ];
        $this->clientProphecy->request('post', "/environments/{$environments[1]->id}/ssl/certificates", $options)
            ->willReturn($sslResponse->{'Site is being imported'}->value)
            ->shouldBeCalled();
        $this->mockNotificationResponseFromObject($sslResponse->{'Site is being imported'}->value);

        $this->executeCommand(
            [
                '--csr-id' => $csrId,
                '--label' => $label,
                '--legacy' => false,
                'certificate' => $certName,
                'private-key' => $keyName,
            ],
            [
                // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'.
                'n',
                // Select a Cloud Platform application: [Sample application 1]:
                0,
                'n',
                1,
                '',
            ]
        );
    }

    public function testCreateCertNode(): void
    {
        $applications = $this->mockRequest('getApplications');
        $application = $this->mockRequest('getApplicationByUuid', $applications[self::$INPUT_DEFAULT_CHOICE]->uuid);
        $tamper = function ($responses): void {
            foreach ($responses as $response) {
                $response->type = 'node';
            }
        };
        $environments = $this->mockRequest('getApplicationEnvironments', $application->uuid, null, null, $tamper);
        $localMachineHelper = $this->mockLocalMachineHelper();
        $certContents = 'cert-contents';
        $keyContents = 'key-contents';
        $certName = 'cert.pem';
        $keyName = 'key.pem';
        $label = 'My certificate';
        $csrId = 123;
        $localMachineHelper->readFile($certName)
            ->willReturn($certContents)
            ->shouldBeCalled();
        $localMachineHelper->readFile($keyName)
            ->willReturn($keyContents)
            ->shouldBeCalled();

        $sslResponse = $this->getMockResponseFromSpec(
            '/environments/{environmentId}/ssl/certificates',
            'post',
            '202'
        );
        $options = [
            'json' => [
                'ca_certificates' => null,
                'certificate' => $certContents,
                'csr_id' => $csrId,
                'label' => $label,
                'legacy' => false,
                'private_key' => $keyContents,
            ],
        ];
        $this->clientProphecy->request('post', "/environments/{$environments[0]->id}/ssl/certificates", $options)
            ->willReturn($sslResponse->{'Site is being imported'}->value)
            ->shouldBeCalled();
        $this->mockNotificationResponseFromObject($sslResponse->{'Site is being imported'}->value);

        $this->executeCommand(
            [
                '--csr-id' => $csrId,
                '--label' => $label,
                '--legacy' => false,
                'certificate' => $certName,
                'private-key' => $keyName,
            ],
            [
                // Would you like Acquia CLI to search for a Cloud application that matches your local git config?'.
                'n',
                // Select a Cloud Platform application: [Sample application 1]:
                0,
            ]
        );
    }
}
