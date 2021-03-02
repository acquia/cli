<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ListCommand.
 */
class ListCommand extends \Symfony\Component\Console\Command\ListCommand {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getArgument('namespace') !== 'api') {
      $all_commands = $this->getApplication()->all();
      foreach ($all_commands as $command) {
        if (strpos($command->getName(), 'api:') !== FALSE && $command->getName() !== 'api:list') {
          if (!in_array($command->getName(), $this->getUnhiddenApiCommands(), TRUE)) {
            $command->setHidden(TRUE);
          }
        }
      }
    }

    $helper = new DescriptorHelper();
    $helper->describe($output, $this->getApplication(), [
      'format' => $input->getOption('format'),
      'raw_text' => $input->getOption('raw'),
      'namespace' => $input->getArgument('namespace'),
    ]);

    return 0;
  }

  /**
   * Show a few of the api commands! Give people a sense of what's there.
   *
   * @return array
   */
  protected function getUnhiddenApiCommands(): array {
    return [
      'api:accounts:find',
      'api:environments:code-deploy',
      'api:environments:database-backup-create',
      'api:environments:domain-clear-varnish',
    ];
  }

}
