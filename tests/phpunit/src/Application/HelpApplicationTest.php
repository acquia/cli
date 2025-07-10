<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Application;

use Acquia\Cli\Tests\ApplicationTestBase;

/**
 * Test the 'help' command.
 *
 * This must be run as an application test since the help command does not
 * inherit from CommandBase and needs access to other commands in the service
 * container.
 */
class HelpApplicationTest extends ApplicationTestBase
{
    /**
     * @group serial
     */
    public function testApplicationAliasHelp(): void
    {
        $this->setInput([
            'command' => 'help',
            'command_name' => 'app:link',
        ]);
        $buffer = $this->runApp();
        $this->assertStringContainsString('The Cloud Platform application UUID or alias (i.e. an application name optionally prefixed with the realm)', $buffer);
        $this->assertStringContainsString('Usage:
  app:link [<applicationUuid>]
  link
  app:link [<applicationAlias>]
  app:link myapp
  app:link prod:myapp
  app:link abcd1234-1111-2222-3333-0e02b2c3d470', $buffer);
    }

    /**
     * @group serial
     */
    public function testEnvironmentAliasHelp(): void
    {
        $this->setInput([
            'command' => 'help',
            'command_name' => 'log:tail',
        ]);
        $buffer = $this->runApp();
        $this->assertStringContainsString('The Cloud Platform environment ID or alias (i.e. an application and environment name optionally prefixed with the realm)', $buffer);
        $this->assertStringContainsString('Usage:
  app:log:tail [<environmentId>]
  tail
  log:tail
  app:log:tail [<environmentAlias>]
  app:log:tail myapp.dev
  app:log:tail prod:myapp.dev
  app:log:tail 12345-abcd1234-1111-2222-3333-0e02b2c3d470', $buffer);
    }

    /**
     * Ensure parameter descriptions defined via additionalProperties are available.
     *
     * @group serial
     */
    public function testApiHelp(): void
    {
        $this->setInput([
            'command' => 'help',
            'command_name' => 'api:environments:cloud-actions-update',
        ]);
        $buffer = $this->runApp();
        $this->assertStringContainsString('Whether this Cloud Action is enabled', $buffer);
    }
}
