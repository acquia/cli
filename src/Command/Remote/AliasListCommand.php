<?php

namespace Acquia\Cli\Command\Remote;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AliasListCommand extends CommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'remote:aliases:list';

  protected function configure(): void {
    $this->setDescription('List all aliases for the Cloud Platform environments')
      ->setAliases(['aliases', 'sa']);
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $applicationsResource = new Applications($acquiaCloudClient);
    $cloudApplicationUuid = $this->determineCloudApplication();
    $customerApplication = $applicationsResource->get($cloudApplicationUuid);
    $environmentsResource = new Environments($acquiaCloudClient);

    $table = new Table($this->output);
    $table->setHeaders(['Application', 'Environment Alias', 'Environment UUID']);

    $siteId = $customerApplication->hosting->id;
    $parts = explode(':', $siteId);
    $sitePrefix = $parts[1];
    $environments = $environmentsResource->getAll($customerApplication->uuid);
    foreach ($environments as $environment) {
      $alias = $sitePrefix . '.' . $environment->name;
      $table->addRow([$customerApplication->name, $alias, $environment->uuid]);
    }

    $table->render();

    return Command::SUCCESS;
  }

}
