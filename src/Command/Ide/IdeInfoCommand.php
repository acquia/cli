<?php

namespace Acquia\Cli\Command\Ide;

use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeListCommand.
 */
class IdeInfoCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:info';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Print information about a Cloud IDE');
    $this->acceptApplicationUuid();
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $application_uuid = $this->determineCloudApplication();

    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);

    $ide = $this->promptIdeChoice("Select an IDE to get more information:", $ides_resource, $application_uuid);
    $response = $ides_resource->get($ide->uuid);
    $this->io->definitionList(
      ['IDE property' => 'IDE value'],
      new TableSeparator(),
      ['UUID' => $response->uuid],
      ['Label' => $response->label],
      ['Owner name' => $response->owner->first_name . ' ' . $response->owner->last_name],
      ['Owner username' => $response->owner->username],
      ['Owner email' => $response->owner->mail],
      ['Cloud application' => $response->links->application->href],
      ['IDE URL' => $response->links->ide->href],
      ['Web URL' => $response->links->web->href]
    );

    return 0;
  }

}
