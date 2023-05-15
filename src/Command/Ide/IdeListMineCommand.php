<?php

namespace Acquia\Cli\Command\Ide;

use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IdeListMineCommand extends IdeCommandBase {

  protected static $defaultName = 'ide:list:mine';

  protected function configure(): void {
    $this->setDescription('List Cloud IDEs belonging to you');
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $ides = new Ides($acquiaCloudClient);
    $accountIdes = $ides->getMine();
    $applicationResource = new Applications($acquiaCloudClient);

    if (count($accountIdes)) {
      $table = new Table($output);
      $table->setStyle('borderless');
      $table->setHeaders(['IDEs']);
      foreach ($accountIdes as $ide) {
        $appUrlParts = explode('/', $ide->links->application->href);
        $appUuid = end($appUrlParts);
        $application = $applicationResource->get($appUuid);
        $applicationUrl = str_replace('/api', '/a', $application->links->self->href);

        $table->addRows([
          ["<comment>$ide->label</comment>"],
          ["UUID: $ide->uuid"],
          ["Application: <href=$applicationUrl>$application->name</>"],
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
