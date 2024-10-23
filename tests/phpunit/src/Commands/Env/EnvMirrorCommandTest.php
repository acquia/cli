<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Env;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Env\EnvMirrorCommand;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Tests\CommandTestBase;
use Prophecy\Argument;

/**
 * @property \Acquia\Cli\Command\Env\EnvMirrorCommand $command
 */
class EnvMirrorCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(EnvMirrorCommand::class);
    }

    public function testEnvironmentMirror(): void
    {
        $environmentResponse = $this->mockGetEnvironments();
        $codeSwitchResponse = self::getMockResponseFromSpec("/environments/{environmentId}/code/actions/switch", 'post', '202');
        $response = $codeSwitchResponse->{'Switching code'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request(
            'post',
            "/environments/$environmentResponse->id/code/actions/switch",
            [
                'form_params' => [
                    'branch' => $environmentResponse->vcs->path,
                ],
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $databasesResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'get', '200');
        $this->clientProphecy->request(
            'get',
            "/environments/$environmentResponse->id/databases"
        )
            ->willReturn($databasesResponse->_embedded->items)
            ->shouldBeCalled();

        $dbCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'post', '202');
        $response = $dbCopyResponse->{'Database being copied'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/databases", [
            'json' => [
                'name' => $databasesResponse->_embedded->items[0]->name,
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $filesCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/files", 'post', '202');
        $response = $filesCopyResponse->{'Files queued for copying'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/files", [
            'json' => [
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $environmentUpdateResponse = self::getMockResponseFromSpec("/environments/{environmentId}", 'put', '202');
        $this->clientProphecy->request('put', "/environments/$environmentResponse->id", Argument::type('array'))
            ->willReturn($environmentUpdateResponse)
            ->shouldBeCalled();
        $this->mockNotificationResponseFromObject($environmentUpdateResponse);

        $this->executeCommand(
            [
                'destination-environment' => $environmentResponse->id,
                'source-environment' => $environmentResponse->id,
            ],
            [
                // Are you sure that you want to overwrite everything ...
                'y',
            ]
        );

        $output = $this->getDisplay();
        $this->assertEquals(0, $this->getStatusCode());
        $this->assertStringContainsString('Are you sure that you want to overwrite everything on Dev (dev) and replace it with source data from Dev (dev)', $output);
        $this->assertStringContainsString("Switching to {$environmentResponse->vcs->path}", $output);
        $this->assertStringContainsString("Copying {$databasesResponse->_embedded->items[0]->name}", $output);
        $this->assertStringContainsString("Copying PHP version, acpu memory limit, etc.", $output);
        $this->assertStringContainsString("[OK] Done! $environmentResponse->label now matches $environmentResponse->label", $output);
    }

    public function testEnvironmentMirrorDbCopyFail(): void
    {
        $environmentResponse = $this->mockGetEnvironments();
        $codeSwitchResponse = self::getMockResponseFromSpec("/environments/{environmentId}/code/actions/switch", 'post', '202');
        $response = $codeSwitchResponse->{'Switching code'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request(
            'post',
            "/environments/$environmentResponse->id/code/actions/switch",
            [
                'form_params' => [
                    'branch' => $environmentResponse->vcs->path,
                ],
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $databasesResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'get', '200');
        $this->clientProphecy->request(
            'get',
            "/environments/$environmentResponse->id/databases"
        )
            ->willReturn($databasesResponse->_embedded->items)
            ->shouldBeCalled();

        $dbCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'post', '202');
        $response = $dbCopyResponse->{'Database being copied'}->value;
        $this->mockNotificationResponseFromObject($response, false);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/databases", [
            'json' => [
                'name' => $databasesResponse->_embedded->items[0]->name,
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $filesCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/files", 'post', '202');
        $response = $filesCopyResponse->{'Files queued for copying'}->value;
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/files", [
            'json' => [
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $environmentUpdateResponse = self::getMockResponseFromSpec("/environments/{environmentId}", 'put', '202');
        $this->clientProphecy->request('put', "/environments/$environmentResponse->id", Argument::type('array'))
            ->willReturn($environmentUpdateResponse)
            ->shouldBeCalled();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Cloud API failed to copy database');
        $this->executeCommand(
            [
                'destination-environment' => $environmentResponse->id,
                'source-environment' => $environmentResponse->id,
            ],
            [
                // Are you sure that you want to overwrite everything ...
                'y',
            ]
        );
    }

    public function testEnvironmentMirrorFileCopyFail(): void
    {
        $environmentResponse = $this->mockGetEnvironments();
        $codeSwitchResponse = self::getMockResponseFromSpec("/environments/{environmentId}/code/actions/switch", 'post', '202');
        $response = $codeSwitchResponse->{'Switching code'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request(
            'post',
            "/environments/$environmentResponse->id/code/actions/switch",
            [
                'form_params' => [
                    'branch' => $environmentResponse->vcs->path,
                ],
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $databasesResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'get', '200');
        $this->clientProphecy->request(
            'get',
            "/environments/$environmentResponse->id/databases"
        )
            ->willReturn($databasesResponse->_embedded->items)
            ->shouldBeCalled();

        $dbCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'post', '202');
        $response = $dbCopyResponse->{'Database being copied'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/databases", [
            'json' => [
                'name' => $databasesResponse->_embedded->items[0]->name,
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $filesCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/files", 'post', '202');
        $response = $filesCopyResponse->{'Files queued for copying'}->value;
        $this->mockNotificationResponseFromObject($response, false);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/files", [
            'json' => [
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $environmentUpdateResponse = self::getMockResponseFromSpec("/environments/{environmentId}", 'put', '202');
        $this->clientProphecy->request('put', "/environments/$environmentResponse->id", Argument::type('array'))
            ->willReturn($environmentUpdateResponse)
            ->shouldBeCalled();

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Cloud API failed to copy files');
        $this->executeCommand(
            [
                'destination-environment' => $environmentResponse->id,
                'source-environment' => $environmentResponse->id,
            ],
            [
                // Are you sure that you want to overwrite everything ...
                'y',
            ]
        );
    }

    public function testEnvironmentMirrorFiCopyFail(): void
    {
        $environmentResponse = $this->mockGetEnvironments();
        $codeSwitchResponse = self::getMockResponseFromSpec("/environments/{environmentId}/code/actions/switch", 'post', '202');
        $response = $codeSwitchResponse->{'Switching code'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request(
            'post',
            "/environments/$environmentResponse->id/code/actions/switch",
            [
                'form_params' => [
                    'branch' => $environmentResponse->vcs->path,
                ],
            ]
        )
            ->willReturn($response)
            ->shouldBeCalled();

        $databasesResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'get', '200');
        $this->clientProphecy->request(
            'get',
            "/environments/$environmentResponse->id/databases"
        )
            ->willReturn($databasesResponse->_embedded->items)
            ->shouldBeCalled();

        $dbCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/databases", 'post', '202');
        $response = $dbCopyResponse->{'Database being copied'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/databases", [
            'json' => [
                'name' => $databasesResponse->_embedded->items[0]->name,
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $filesCopyResponse = self::getMockResponseFromSpec("/environments/{environmentId}/files", 'post', '202');
        $response = $filesCopyResponse->{'Files queued for copying'}->value;
        $this->mockNotificationResponseFromObject($response);
        $response->links = $response->{'_links'};
        $this->clientProphecy->request('post', "/environments/$environmentResponse->id/files", [
            'json' => [
                'source' => $environmentResponse->id,
            ],
        ])
            ->willReturn($response)
            ->shouldBeCalled();

        $environmentUpdateResponse = self::getMockResponseFromSpec("/environments/{environmentId}", 'put', '202');
        $this->clientProphecy->request('put', "/environments/$environmentResponse->id", Argument::type('array'))
            ->willReturn($environmentUpdateResponse)
            ->shouldBeCalled();
        $this->mockNotificationResponseFromObject($environmentUpdateResponse, false);

        $this->expectException(AcquiaCliException::class);
        $this->expectExceptionMessage('Cloud API failed to copy config');
        $this->executeCommand(
            [
                'destination-environment' => $environmentResponse->id,
                'source-environment' => $environmentResponse->id,
            ],
            [
                // Are you sure that you want to overwrite everything ...
                'y',
            ]
        );
    }
}
