<?php

namespace Acquia\Cli\Command\Ide;

use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeListMineCommand.
 */
class IdeListMineCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:list:mine';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('List Cloud IDEs belonging to you');
    $this->acceptApplicationUuid();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides = new Ides($acquia_cloud_client);
    $account_ides = $ides->getMine();
    $application_resource = new Applications($acquia_cloud_client);

    if (count($account_ides)) {
      $table = new Table($output);
      $table->setStyle('borderless');
      $table->setHeaders(['IDEs']);
      foreach ($account_ides as $ide) {
        $app_url_parts = explode('/', $ide->links->application->href);
        $app_uuid = end($app_url_parts);
        $application = $application_resource->get($app_uuid);
        $application_url = str_replace('/api', '/a', $application->links->self->href);

        $table->addRows([
          ["<comment>{$ide->label}</comment>"],
          ["UUID: {$ide->uuid}"],
          ["Application: <href={$application_url}>{$application->name}</>"],
          ["Subscription: {$application->subscription->name}"],
          ["IDE URL: <href={$ide->links->ide->href}>{$ide->links->ide->href}</>"],
          ["Web URL: <href={$ide->links->web->href}>{$ide->links->web->href}</>"],
          new TableSeparator(),
        ]);
      }
      $table->render();
    }
    else {
      $output->writeln('No IDE exists for your account.');
    }

    return 0;
  }

}
