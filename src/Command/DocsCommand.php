<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class DocsCommand.
 */
class DocsCommand extends CommandBase {

  protected static $defaultName = 'docs';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Open Acquia products documentation in a web browser');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_products = [
      'Acquia CLI' => 'acquia-cli',
      'Acquia CMS' => 'acquia-cms',
      'Code Studio' => 'code-studio',
      'Campaign Studio' => 'campaign-studio',
      'Content Hub' => 'contenthub',
      'Acquia Migrate Accelerate' => 'acquia-migrate-accelerate',
      'Site Factory' => 'site-factory',
      'Site Studio' => 'site-studio',
      'Edge' => 'edge',
      'Search' => 'acquia-search',
      'Shield' => 'shield',
      'Customer Data Plateform' => 'customer-data-platform',
      'Cloud IDE' => 'ide',
    ];

    $labels = array_keys($acquia_products);
    $question = new ChoiceQuestion('Please select the Acquia Product', $labels, $labels[0]);
    $choice_id = $this->io->askQuestion($question);
    $this->localMachineHelper->startBrowser('https://docs.acquia.com/' . $acquia_products[$choice_id] . '/');

    return 0;
  }

}
