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

class AppVcsInfo extends CommandBase {

  protected static $defaultName = 'app:vcs:info';

  protected function configure(): void {
    $this->setDescription('Get all branches and tags of the application with the deployment status')
      ->addOption('deployed', NULL, InputOption::VALUE_OPTIONAL, 'Show only deployed branches and tags')
      ->addUsage('[<applicationAlias>] --deployed');
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $applicationUuid = $this->determineCloudApplication();

    $cloudApiClient = $this->cloudApiClientService->getClient();

    $envResource = new Environments($cloudApiClient);
    $environments = $envResource->getAll($applicationUuid);

    if (!$environments->count()) {
      throw new AcquiaCliException('There are no environments available with this application.');
    }

    // To show branches and tags which are deployed only.
    $showDeployedVcsOnly = $input->hasParameterOption('--deployed');

    // Prepare list of all deployed VCS paths.
    $deployedVcs = [];
    foreach ($environments as $environment) {
      if (isset($environment->vcs->path)) {
        $deployedVcs[$environment->vcs->path] = $environment->label;
      }
    }

    // If only to show the deployed VCS but no VCS is deployed.
    if ($showDeployedVcsOnly && empty($deployedVcs)) {
      throw new AcquiaCliException('No branch or tag is deployed on any of the environment of this application.');
    }

    $applicationCodeResource = new Code($cloudApiClient);
    $allBranchesAndTags = $applicationCodeResource->getAll($applicationUuid);

    if (!$allBranchesAndTags->count()) {
      throw new AcquiaCliException('No branch or tag is available with this application.');
    }

    $nonDeployedVcs = [];
    // Show both deployed and non-deployed VCS.
    if (!$showDeployedVcsOnly) {
      // Prepare list of all non-deployed VCS paths.
      foreach ($allBranchesAndTags as $branchTag) {
        if (!isset($deployedVcs[$branchTag->name])) {
          $nonDeployedVcs[$branchTag->name] = $branchTag->name;
        }
      }
    }

    // To show the deployed VCS paths on top.
    $allVcs = array_merge($deployedVcs, $nonDeployedVcs);
    $headers = ['Branch / Tag Name', 'Deployed', 'Deployed Environment'];
    $table = new Table($output);
    $table->setHeaders($headers);
    $table->setHeaderTitle('Status of Branches and Tags of the Application');
    foreach ($allVcs as $vscPath => $env) {
      $table->addRow([
        $vscPath,
        // If VCS and env name is not same, it means it is deployed.
        $vscPath !== $env ? 'Yes' : 'No',
        // If VCS and env name is same, it means it is deployed.
        $vscPath !== $env ? $env : 'None',
      ]);
    }

    $table->render();
    $this->io->newLine();

    return self::SUCCESS;
  }

}
