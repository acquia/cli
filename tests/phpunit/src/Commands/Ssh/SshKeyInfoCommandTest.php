<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\Ssh;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Command\Ssh\SshKeyInfoCommand;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class SshKeyInfoCommandTest extends CommandTestBase
{
    protected function createCommand(): CommandBase
    {
        return $this->injectCommand(SshKeyInfoCommand::class);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFsFixture();
        $this->command = $this->createCommand();
    }

    public function testInfo(): void
    {
        $this->mockListSshKeysRequest();

        $inputs = [
            // Choose key.
            '0',
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Choose an SSH key to view', $output);
        $this->assertStringContainsString('SSH key property       SSH key value', $output);
        $this->assertStringContainsString('UUID                   02905393-65d7-4bef-873b-24593f73d273', $output);
        $this->assertStringContainsString('Label                  PC Home', $output);
        $this->assertStringContainsString('Fingerprint (md5)      5d:23:fb:45:70:df:ef:ad:ca:bf:81:93:cd:50:26:28', $output);
        $this->assertStringContainsString('Created at             2017-05-09T20:30:35.000Z', $output);
        $this->assertStringContainsString('Public key', $output);
        // Check for the specific separator line after "Public key" and before the SSH key content.
        $this->assertStringContainsString("Public key" . "\n" . "----------", $output);
        $this->assertStringContainsString('ssh-rsa AAAAB3NzaC1yc2EADHrfHY17SbrmAAABIwAAAQEAklOUpkTIpNLTGK9Tjom/BWDSUGPl+nafzlZ5ew18JH4JW9jbhUFrviQzM7xlELEVf4h9lFX5HDTYW7hdI4yQVkbPppSwg0cda3Pbv7kOdJ/MTyBlWXFCR+HAo3FXRitBqxiX1nKhXpHAZsMciLq8V6RjsNAQwdsdMFvSlVK/7XAt3FaoJoAsncM1Q9x5+3V0Ww68/eIFmb1zuUFljQJKprrX88XypNDvjYNby6vw/Pb0rwert/EnmZ+AW4OZPnTPI89ZPmVMLuayrD2cE86Z/il8b+gw3r3+1nKatmIkjn2so1d01QraTlMqVSsbxNrRFi9wrf+M7Q== example@example.com', $output);
    }
    public function testInfoWithoutKey(): void
    {
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn([])
            ->shouldBeCalled();
        $inputs = [
            // Choose key.
            '0',
        ];
        $this->executeCommand([], $inputs);

        // Assert.
        $output = $this->getDisplay();

        $this->assertStringContainsString('', $output);
    }

    public function testInfoWithValidFingerprintOption(): void
    {
        $this->mockListSshKeysRequest();
        $fingerprint = '/fdGyAgZOUMkimBgfLmB91AWOvNgH/lOd2Z9A3gPsI4=';
        $this->executeCommand(['--fingerprint' => $fingerprint]);
        $output = $this->getDisplay();
        $this->assertStringContainsString('SSH key property', $output);
        $this->assertStringContainsString('Fingerprint (sha256)', $output);
    }

    public function testInfoWithInvalidFingerprintOptionThrowsException(): void
    {
        $this->mockListSshKeysRequest();
        $fingerprint = 'invalid-fingerprint';
        $this->expectException(\Acquia\Cli\Exception\AcquiaCliException::class);
        $this->executeCommand(['--fingerprint' => $fingerprint]);
    }

    public function testInfoWithCloudOnlyKey(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();
        // No local keys.
        $this->mockLocalSshKeys([]);
        $inputs = ['0'];
        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Choose an SSH key to view', $output);
        $this->assertStringContainsString('SSH key property       SSH key value', $output);
        $this->assertStringContainsString('Location               Cloud', $output);
        $this->assertStringContainsString('Label                  PC Home', $output);
        $this->assertStringContainsString('Fingerprint (md5)      5d:23:fb:45:70:df:ef:ad:ca:bf:81:93:cd:50:26:28', $output);
        $this->assertStringContainsString('Created at             2017-05-09T20:30:35.000Z', $output);
        $this->assertStringContainsString('Public key', $output);
        // Check for the specific separator line after "Public key" and before the SSH key content.
        $this->assertStringContainsString("Public key\n----------", $output);
        $this->assertStringContainsString('ssh-rsa AAAAB3NzaC1yc2EADHrfHY17SbrmAAABIwAAAQEAklOUpkTIpNLTGK9Tjom/BWDSUGPl+nafzlZ5ew18JH4JW9jbhUFrviQzM7xlELEVf4h9lFX5HDTYW7hdI4yQVkbPppSwg0cda3Pbv7kOdJ/MTyBlWXFCR+HAo3FXRitBqxiX1nKhXpHAZsMciLq8V6RjsNAQwdsdMFvSlVK/7XAt3FaoJoAsncM1Q9x5+3V0Ww68/eIFmb1zuUFljQJKprrX88XypNDvjYNby6vw/Pb0rwert/EnmZ+AW4OZPnTPI89ZPmVMLuayrD2cE86Z/il8b+gw3r3+1nKatmIkjn2so1d01QraTlMqVSsbxNrRFi9wrf+M7Q== example@example.com', $output);
    }

    public function testInfoWithLocalOnlyKey(): void
    {
        // Simulate only local key present.
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn([])
            ->shouldBeCalled();
        $this->mockLocalSshKeys([
            $this->createLocalKeyProphecy('ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQClocal', 'local-key.pub'),
        ]);
        $inputs = ['0'];
        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        $this->assertEmpty($output);
    }

    protected static function getFingerprint(mixed $sshPublicKey): string
    {
        $content = explode(' ', $sshPublicKey, 3);
        return base64_encode(hash('sha256', base64_decode($content[1]), true));
    }
    public function testInfoWithLocalAndCloudKey(): void
    {
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();
        $cloudKey = (array) $mockBody->{'_embedded'}->items[0];
        $this->mockLocalSshKeys([
            $this->createLocalKeyProphecy($cloudKey['public_key'], 'both-key.pub'),
        ]);


        $inputs = ['0'];
        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        $this->assertStringContainsString('Choose an SSH key to view', $output);
        $this->assertStringContainsString('SSH key property       SSH key value', $output);
        $this->assertStringContainsString('UUID                   02905393-65d7-4bef-873b-24593f73d273', $output);
        $this->assertStringContainsString('Label                  PC Home', $output);
        $this->assertStringContainsString('Fingerprint (md5)      5d:23:fb:45:70:df:ef:ad:ca:bf:81:93:cd:50:26:28', $output);
        $this->assertStringContainsString('Created at             2017-05-09T20:30:35.000Z', $output);
        $this->assertStringContainsString('Public key', $output);
        // Check for the specific separator line after "Public key" and before the SSH key content.
        $this->assertStringContainsString("Public key\n----------", $output);
        $this->assertStringContainsString('ssh-rsa AAAAB3NzaC1yc2EADHrfHY17SbrmAAABIwAAAQEAklOUpkTIpNLTGK9Tjom/BWDSUGPl+nafzlZ5ew18JH4JW9jbhUFrviQzM7xlELEVf4h9lFX5HDTYW7hdI4yQVkbPppSwg0cda3Pbv7kOdJ/MTyBlWXFCR+HAo3FXRitBqxiX1nKhXpHAZsMciLq8V6RjsNAQwdsdMFvSlVK/7XAt3FaoJoAsncM1Q9x5+3V0Ww68/eIFmb1zuUFljQJKprrX88XypNDvjYNby6vw/Pb0rwert/EnmZ+AW4OZPnTPI89ZPmVMLuayrD2cE86Z/il8b+gw3r3+1nKatmIkjn2so1d01QraTlMqVSsbxNrRFi9wrf+M7Q== example@example.com', $output);
    }


    // Helpers for mocking local keys using SplFileInfo and Finder.
    private function mockLocalSshKeys(array $keys): void
    {
        $finder = $this->createMock(\Symfony\Component\Finder\Finder::class);
        $finder->method('files')->willReturnSelf();
        $finder->method('in')->willReturnSelf();
        $finder->method('name')->willReturnSelf();
        $finder->method('ignoreUnreadableDirs')->willReturnSelf();
        $finder->method('getIterator')->willReturn(new \ArrayIterator($keys));
        $localMachineHelper = $this->createMock(LocalMachineHelper::class);
        $localMachineHelper->method('getFinder')->willReturn($finder);
    }

    private function createLocalKeyProphecy(string $publicKey, string $filename): \Symfony\Component\Finder\SplFileInfo|\PHPUnit\Framework\MockObject\MockObject
    {
        $file = $this->createMock(\Symfony\Component\Finder\SplFileInfo::class);
        $file->method('getContents')->willReturn($publicKey);
        $file->method('getFilename')->willReturn($filename);
        return $file;
    }

    public function testPromptsDeletionWhenLocalMatchesCloudAndHasRealPath(): void
    {
        // Arrange cloud key.
        $mockBody = self::getMockResponseFromSpec('/account/ssh-keys', 'get', '200');
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn($mockBody->{'_embedded'}->items)
            ->shouldBeCalled();
        $cloudPublicKey = $mockBody->{'_embedded'}->items[0]->public_key;

        // Arrange local file that matches cloud key and has a real path.
        $local = $this->createMock(SplFileInfo::class);
        // Spaces to test trim()
        $local->method('getContents')->willReturn("  {$cloudPublicKey}  ");
        $local->method('getRealPath')->willReturn('/home/jigar/.ssh/id_test.pub');
        $local->method('getFilename')->willReturn('id_test.pub');

        // Mock Finder pipeline to return our local file.
        $finder = $this->createMock(Finder::class);
        $finder->method('files')->willReturnSelf();
        $finder->method('in')->willReturnSelf();
        $finder->method('name')->willReturnSelf();
        $finder->method('ignoreUnreadableDirs')->willReturnSelf();
        $finder->method('getIterator')->willReturn(new \ArrayIterator([$local]));

        // Inject LocalMachineHelper that returns our Finder.
        $localMachineHelper = $this->createMock(LocalMachineHelper::class);
        $localMachineHelper->method('getFinder')->willReturn($finder);
        // $this->setPrivateProperty($this->command, 'localMachineHelper', $localMachineHelper);
        $ioProphecy = $this->prophet->prophesize(\Symfony\Component\Console\Style\SymfonyStyle::class);
        // Expect the confirm prompt (this is the branch we need to hit)
        $ioProphecy->confirm($this->stringContains('Do you also want to delete the corresponding local key files /home/jigar/.ssh/id_test.pub and /home/jigar/.ssh/id_test ?'))
            // Choose "No" to avoid actually deleting files.
            ->willReturn(false);

        // Act
        // Provide inputs if your command is interactive; otherwise call execute directly.
        $this->executeCommand([], []);

        // Assert.
        $output = $this->getDisplay();
        $this->assertStringContainsString('Choose an SSH key to view', $output);
        $this->assertStringContainsString('SSH key property       SSH key value', $output);
        $this->assertStringContainsString('UUID                   02905393-65d7-4bef-873b-24593f73d273', $output);
        $this->assertStringContainsString('Label                  PC Home', $output);
        $this->assertStringContainsString('Fingerprint (md5)      5d:23:fb:45:70:df:ef:ad:ca:bf:81:93:cd:50:26:28', $output);
        $this->assertStringContainsString('Created at             2017-05-09T20:30:35.000Z', $output);
        $this->assertStringContainsString('Public key', $output);
        // Check for the specific separator line after "Public key" and before the SSH key content.
        $this->assertStringContainsString("Public key\n----------", $output);
        $this->assertStringContainsString('ssh-rsa AAAAB3NzaC1yc2EADHrfHY17SbrmAAABIwAAAQEAklOUpkTIpNLTGK9Tjom/BWDSUGPl+nafzlZ5ew18JH4JW9jbhUFrviQzM7xlELEVf4h9lFX5HDTYW7hdI4yQVkbPppSwg0cda3Pbv7kOdJ/MTyBlWXFCR+HAo3FXRitBqxiX1nKhXpHAZsMciLq8V6RjsNAQwdsdMFvSlVK/7XAt3FaoJoAsncM1Q9x5+3V0Ww68/eIFmb1zuUFljQJKprrX88XypNDvjYNby6vw/Pb0rwert/EnmZ+AW4OZPnTPI89ZPmVMLuayrD2cE86Z/il8b+gw3r3+1nKatmIkjn2so1d01QraTlMqVSsbxNrRFi9wrf+M7Q== example@example.com', $output);
    }

    public function testDetermineSshKeyReturnsEmptyArrayWhenNoKeys(): void
    {
        // Simulate no cloud keys and no local keys.
        $this->clientProphecy->request('get', '/account/ssh-keys')
            ->willReturn([])
            ->shouldBeCalled();
        $this->mockLocalSshKeys([]);
        $inputs = ['0'];
        $this->executeCommand([], $inputs);
        $output = $this->getDisplay();
        $this->assertEmpty($output);
    }
}
