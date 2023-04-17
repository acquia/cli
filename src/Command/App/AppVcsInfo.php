<?php

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Code;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AppVcsInfo.
 */
class AppVcsInfo extends CommandBase {

  protected static $defaultName = 'app:vcs:info';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Get all branches and tags of the application with the deployment status')
      ->addOption('deployed', NULL, InputOption::VALUE_OPTIONAL, 'Show only deployed branches and tags')
      ->addUsage(self::getDefaultName() . ' [<applicationAlias>] --deployed');
    $this->acceptApplicationUuid();
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $application_uuid = $this->determineCloudApplication();

    $cloud_api_client = $this->cloudApiClientService->getClient();

    $env_resource = new Environments($cloud_api_client);
    $environments = $env_resource->getAll($application_uuid);

    if (!$environments->count()) {
      throw new AcquiaCliException('There are no environments available with this application.');
    }

    // To show branches and tags which are deployed only.
    $show_deployed_vcs_only = $input->hasParameterOption('--deployed');

    // Prepare list of all deployed VCS paths.
    $deployed_vcs = [];
    foreach ($environments as $environment) {
      if (isset($environment->vcs->path)) {
        $deployed_vcs[$environment->vcs->path] = $environment->label;
      }
    }

    // If only to show the deployed VCS but no VCS is deployed.
    if ($show_deployed_vcs_only && empty($deployed_vcs)) {
      throw new AcquiaCliException('No branch or tag is deployed on any of the environment of this application.');
    }

    $application_code_resource = new Code($cloud_api_client);
    $all_branches_and_tags = $application_code_resource->getAll($application_uuid);

    if (!$all_branches_and_tags->count()) {
      throw new AcquiaCliException('No branch or tag is available with this application.');
    }

    $non_deployed_vcs = [];
    // Show both deployed and non-deployed VCS.
    if (!$show_deployed_vcs_only) {
      // Prepare list of all non-deployed VCS paths.
      foreach ($all_branches_and_tags as $branch_tag) {
        if (!isset($deployed_vcs[$branch_tag->name])) {
          $non_deployed_vcs[$branch_tag->name] = $branch_tag->name;
        }
      }
    }

    // To show the deployed VCS paths on top.
    $all_vcs = array_merge($deployed_vcs, $non_deployed_vcs);
    $headers = ['Branch / Tag Name', 'Deployed', 'Deployed Environment'];
    $table = new Table($output);
    $table->setHeaders($headers);
    $table->setHeaderTitle('Status of Branches and Tags of the Application');
    foreach ($all_vcs as $vsc_path => $env) {
      $table->addRow([
        $vsc_path,
        // If VCS and env name is not same, it means it is deployed.
        $vsc_path !== $env ? 'Yes' : 'No',
        // If VCS and env name is same, it means it is deployed.
        $vsc_path !== $env ? $env : 'None',
      ]);
    }

    $table->render();
    $this->io->newLine();

    return self::SUCCESS;
  }

}
