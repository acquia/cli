<?php

namespace Acquia\Cli\Command\Organization;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Organizations;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class OrganizationMembersRemoveCommand.
 */
class OrganizationMembersRemoveCommand extends CommandBase {

  protected static $defaultName = 'org:member-remove';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Remove the members from an organiztaion on Acquia Cloud Platform')
      ->addArgument('organization_id', InputArgument::REQUIRED, 'ID of the organization')
      ->addArgument('user_emails', InputArgument::REQUIRED, 'Comma separated emails of the users to be removed from organization')
      ->addUsage(self::getDefaultName() . ' 1e7efab9-0fac-4a2c-ad94-61efc78623ba mytest@email.com')
      ->addUsage(self::getDefaultName() . ' 1e7efab9-0fac-4a2c-ad94-61efc78623ba mytest1@email.com,mytest2@email.com');
  }

  /**
   * {@inheritdoc }
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $org_id = $input->getArgument('organization_id');
    $email_ids = explode(',', $input->getArgument('user_emails'));

    // Get the organization resource.
    $organization_resource = new Organizations($this->cloudApiClientService->getClient());
    $all_organizations = $organization_resource->getAll();

    if (count($all_organizations) === 0) {
      $this->io->error('No organization available with your account.');
      return self::FAILURE;
    }

    $is_valid_org_uuid = FALSE;
    foreach ($all_organizations as $organization) {
      if ($organization->uuid == $org_id) {
        $is_valid_org_uuid = TRUE;
        break;
      }
    }

    if (!$is_valid_org_uuid) {
      $this->io->error('No organization with the given organization id or you don\'t have access.');
      return self::FAILURE;
    }

    $org_members = $organization_resource->getMembers($org_id);
    $org_admins = $organization_resource->getAdmins($org_id);

    // If no member/admin available in organization
    if (count($org_members) === 0 && count($org_admins) === 0) {
      $this->io->error('No member available in the organization.');
      return self::FAILURE;
    }

    $all_organization_members = [];
    foreach ($org_members as $member) {
      $all_organization_members['member'][$member->mail] = $member->uuid;
    }

    foreach ($org_admins as $admin) {
      $all_organization_members['admin'][$admin->mail] = $admin->uuid;
    }

    $removal_emails = [];
    $not_removal_emails = [];
    foreach ($email_ids as $email_id) {
      if (isset($all_organization_members['member'][$email_id])) {
        $removal_emails[$email_id] = $all_organization_members['member'][$email_id];
      }
      elseif (isset($all_organization_members['admin'][$email_id])) {
        $removal_emails[$email_id] = $all_organization_members['admin'][$email_id];
      }
      else {
        $not_removal_emails[] = $email_id;
      }
    }

    $deleted_emails = [];
    $not_deleted_emails = [];
    if (!empty($removal_emails)) {
      foreach ($removal_emails as $mail => $member_id) {
        try {
          $organization_resource->deleteMember($org_id, $member_id);
          $deleted_emails[] = $mail;
        }
        catch (\Exception $e) {
          $not_deleted_emails[] = $mail;
          $this->logger->error('Email @email could not removed because of the error: @error', [
            '@email' => $mail,
            '@error' => $e->getMessage(),
          ]);
        }
      }
    }

    if (!empty($deleted_emails)) {
      $this->io->success('The following email addresses are removed from the organization: ' . implode(', ', $deleted_emails));
    }

    if (!empty($not_deleted_emails)) {
      $this->io->error('The following email addresses are not removed from the organization: ' . implode(', ', $deleted_emails));
    }

    if (!empty($not_removal_emails)) {
      $this->io->error('The following email addresses are not available in the organization: ' . implode(', ', $not_removal_emails));
    }

    return self::SUCCESS;
  }

}
