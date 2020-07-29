<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeListCommand.
 */
class IdeListCommand extends CommandBase {

  protected static $defaultName = 'ide:list';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('List available Cloud IDEs')
    ->addOption('cloud-app-uuid', 'uuid', InputOption::VALUE_REQUIRED, 'The UUID of the associated Acquia Cloud Application');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $application_uuid = $this->determineCloudApplication();

    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $application_ides = $ides_resource->getAll($application_uuid);

    if ($application_ides->count()) {
      $table = new Table($output);
      $table->setStyle('borderless');
      $table->setHeaders(['IDEs']);
      foreach ($application_ides as $ide) {
        $table->addRows([
          ["<comment>{$ide->label} ({$ide->owner->mail})</comment>"],
          ["IDE URL: <href={$ide->links->ide->href}>{$ide->links->ide->href}</>"],
          ["Web URL: <href={$ide->links->web->href}>{$ide->links->web->href}</>"],
          new TableSeparator(),
        ]);
      }
      $table->render();
    }
    else {
      $output->writeln('No IDE exists for this application.');
    }

    return 0;
  }

}
