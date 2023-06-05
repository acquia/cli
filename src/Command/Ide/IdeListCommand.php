<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Ide;

use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IdeListCommand extends IdeCommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'ide:list:app';

  protected function configure(): void {
    $this->setDescription('List available Cloud IDEs belonging to a given application');
    $this->setAliases(['ide:list']);
    $this->acceptApplicationUuid();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $applicationUuid = $this->determineCloudApplication();

    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $idesResource = new Ides($acquiaCloudClient);
    $applicationIdes = $idesResource->getAll($applicationUuid);

    if ($applicationIdes->count()) {
      $table = new Table($output);
      $table->setStyle('borderless');
      $table->setHeaders(['IDEs']);
      foreach ($applicationIdes as $ide) {
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

    return Command::SUCCESS;
  }

}
