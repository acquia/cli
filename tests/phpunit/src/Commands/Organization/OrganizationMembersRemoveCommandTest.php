<?php

namespace Acquia\Cli\Tests\Commands\Organization;

use Acquia\Cli\Command\Organization\OrganizationMembersRemoveCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class OrganizationMembersRemoveCommandTest.
 *
 * @property \Acquia\Cli\Command\Organization\OrganizationMembersRemoveCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class OrganizationMembersRemoveCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(OrganizationMembersRemoveCommand::class);
  }

  /**
   * Tests no organization available for the account.
   */
  public function testNoOrganizationWithAccount(): void {
    $organizations_response = $this->getMockResponseFromSpec('/organizations', 'get', '200');
    $organizations_response->{'_embedded'}->items = [];
    $this->clientProphecy->request('get',
      '/organizations')
      ->willReturn($organizations_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'organization_id' => 'bfafd31a-83a6-4257-b0ec-afdeff83117a',
        'user_emails' => 'test1@test.com',
      ],
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('No organization available with your account.', $output);
  }

  /**
   * Tests the given organization not exists for the account.
   */
  public function testOrganizationNotExistsWithAccount(): void {
    $organizations_response = $this->getMockResponseFromSpec('/organizations', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations')
      ->willReturn($organizations_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'organization_id' => 'SOME-WRONG-ORGANIZATION-ID',
        'user_emails' => 'test1@test.com',
      ],
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('No organization with the given organization id or you don\'t have access', $output);
  }

  /**
   * Tests no member available for the given organization.
   */
  public function testNoMemberAvailableWithOrganization(): void {
    $organizations_response = $this->getMockResponseFromSpec('/organizations', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations')
      ->willReturn($organizations_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $members_list = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', '200');
    $members_list->{'_embedded'}->items = [];
    $this->clientProphecy->request('get',
      '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/members')
      ->willReturn($members_list->{'_embedded'}->items)
      ->shouldBeCalled();

    $admin_list = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/admins', 'get', '200');
    $admin_list->{'_embedded'}->items = [];
    $this->clientProphecy->request('get',
      '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/admins')
      ->willReturn($admin_list->{'_embedded'}->items)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'organization_id' => $organizations_response->{'_embedded'}->items[0]->uuid,
        'user_emails' => 'test1@test.com',
      ],
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('No member available in the organization.', $output);
  }

  /**
   * Tests member not available in organization.
   */
  public function testMemberNotAvailableInOrganization(): void {
    $organizations_response = $this->getMockResponseFromSpec('/organizations', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations')
      ->willReturn($organizations_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $members_list = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/members')
      ->willReturn($members_list->{'_embedded'}->items)
      ->shouldBeCalled();

    $admin_list = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/admins', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/admins')
      ->willReturn($admin_list->{'_embedded'}->items)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'organization_id' => $organizations_response->{'_embedded'}->items[0]->uuid,
        'user_emails' => 'test1@test.com,test2@test.com',
      ],
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('The following email addresses are not available in the organization:', $output);
  }

  /**
   * Tests member removed from the organization.
   */
  public function testMemberRemovedFromOrganization(): void {
    $organizations_response = $this->getMockResponseFromSpec('/organizations', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations')
      ->willReturn($organizations_response->{'_embedded'}->items)
      ->shouldBeCalled();

    $members_list = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/members', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/members')
      ->willReturn($members_list->{'_embedded'}->items)
      ->shouldBeCalled();

    $admin_list = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/admins', 'get', '200');
    $this->clientProphecy->request('get',
      '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/admins')
      ->willReturn($admin_list->{'_embedded'}->items)
      ->shouldBeCalled();

    $delete_member = $this->getMockResponseFromSpec('/organizations/{organizationUuid}/members/{userUuid}',
      'delete', 200);
    $this->clientProphecy->request('delete', '/organizations/' . $organizations_response->{'_embedded'}->items[0]->uuid . '/members/' . $members_list->{'_embedded'}->items[0]->uuid)
      ->willReturn($delete_member->{'Member removed'}->value)
      ->shouldBeCalled();

    $this->executeCommand(
      [
        'organization_id' => $organizations_response->{'_embedded'}->items[0]->uuid,
        'user_emails' => 'jonathan.archer@example.com,test1@test.com',
      ],
    );

    $output = $this->getDisplay();
    $this->assertStringContainsString('The following email addresses are removed from the organization:', $output);
    $this->assertStringContainsString('The following email addresses are not available in the organization:', $output);
  }

}
