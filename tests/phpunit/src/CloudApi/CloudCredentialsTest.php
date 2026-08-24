<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\CloudApi;

use Acquia\Cli\Tests\TestBase;

class CloudCredentialsTest extends TestBase
{
    public function testGetBaseUriReturnsNullWhenNoStoredUri(): void
    {
        $this->assertNull($this->cloudCredentials->getBaseUri());
        $this->assertNull($this->cloudCredentials->getAccountsUri());
    }

    public function testGetBaseUriReturnsStoredUriWhenNoEnvVar(): void
    {
        $this->datastoreCloud->set('keys', [
            self::$key => [
                'accounts_uri' => 'https://staging.accounts.acquia.com/api/auth/oauth/token',
                'cloud_api_base_uri' => 'https://staging.cloud.acquia.com/api',
                'label' => 'Test Key',
                'secret' => self::$secret,
                'uuid' => self::$key,
            ],
        ]);

        $this->assertSame('https://staging.cloud.acquia.com/api', $this->cloudCredentials->getBaseUri());
        $this->assertSame('https://staging.accounts.acquia.com/api/auth/oauth/token', $this->cloudCredentials->getAccountsUri());
    }

    public function testGetBaseUriEnvVarTakesPriorityOverStoredUri(): void
    {
        $this->datastoreCloud->set('keys', [
            self::$key => [
                'accounts_uri' => 'https://staging.accounts.acquia.com/api/auth/oauth/token',
                'cloud_api_base_uri' => 'https://staging.cloud.acquia.com/api',
                'label' => 'Test Key',
                'secret' => self::$secret,
                'uuid' => self::$key,
            ],
        ]);
        putenv('ACLI_CLOUD_API_BASE_URI=https://qa.cloud.acquia.com/api');
        putenv('ACLI_CLOUD_API_ACCOUNTS_URI=https://qa.accounts.acquia.com/api/auth/oauth/token');

        try {
            $this->assertSame('https://qa.cloud.acquia.com/api', $this->cloudCredentials->getBaseUri());
            $this->assertSame('https://qa.accounts.acquia.com/api/auth/oauth/token', $this->cloudCredentials->getAccountsUri());
        } finally {
            putenv('ACLI_CLOUD_API_BASE_URI');
            putenv('ACLI_CLOUD_API_ACCOUNTS_URI');
        }
    }
}
