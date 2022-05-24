<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Code;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetUsedUnusedBranchTags extends CommandBase {

  protected static $defaultName = 'app:used-unsed-branch-tags';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Get all used and un-used branches and tags of the application.');
    $this->acceptApplicationUuid();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $cloud_application_uuid = $input->getArgument('applicationUuid');
    // If application id is not provided in input.
    if (!$cloud_application_uuid) {
      $cloud_application_uuid = $this->determineCloudApplication();
    }

    $cloud_api_client = $this->cloudApiClientService->getClient();
    $application_code_resource = new Code($cloud_api_client);
    $all_branches_and_tags = $application_code_resource->getAll($cloud_application_uuid);

    // Prepare list of all application tags and branches.
    $all_vcs_paths = ['tags' => [], 'branches' => []];
    foreach ($all_branches_and_tags as $branch_tag) {
      $all_vcs_paths[$branch_tag->flags->tag ? 'tags' : 'branches'][$branch_tag->name] = $branch_tag->name;
    }

    $env_resource = new Environments($cloud_api_client);
    $environments = $env_resource->getAll($cloud_application_uuid);
    $deployed_branches = [];
    // Get tags and branches deployed on each environment
    // of the application.
    foreach ($environments as $environment) {
      $deployed_branches[] = $environment->vcs->path;
      // Remove this deployed branch or tag from the all branch/tag list
      if (isset($all_vcs_paths['tags'][$environment->vcs->path])) {
        unset($all_vcs_paths['tags'][$environment->vcs->path]);
      }
      else {
        unset($all_vcs_paths['branches'][$environment->vcs->path]);
      }
    }

    $this->io->info('List of used branches and tags: ' . implode(', ', $deployed_branches));
    $this->io->info('List of un-used branches: ' . implode(', ', $all_vcs_paths['tags']));
    $this->io->info('List of un-used tags: ' . implode(', ', $all_vcs_paths['branches']));
    return 0;
  }

}
