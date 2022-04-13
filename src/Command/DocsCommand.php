<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class DocsCommand.
 */
class DocsCommand extends ApiCommandBase {

  protected static $defaultName = 'docs';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Open Acquia product documentation in a web browser')
      ->addArgument('product', InputArgument::OPTIONAL, 'Acquia Product Name')
      ->addUsage(self::getDefaultName() . ' acli');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_products = [
      'Acquia CLI' => [
        'url' => 'acquia-cli',
        'alias' => ['cli', 'acli'],
      ],
      'Acquia CMS' => [
        'url' => 'acquia-cms',
        'alias' => ['acquia_cms', 'acms'],
      ],
      'Code Studio' => [
        'url' => 'code-studio',
        'alias' => ['code_studio', 'codestudio', 'cs'],
      ],
      'Campaign Studio' => [
        'url' => 'campaign-studio',
        'alias' => ['campaign-studio', 'campaignstudio'],
      ],
      'Content Hub' => [
        'url' => 'contenthub',
        'alias' => ['contenthub', 'ch'],
      ],
      'Acquia Migrate Accelerate' => [
        'url' => 'acquia-migrate-accelerate',
        'alias' => ['acquia-migrate-accelerate', 'ama'],
      ],
      'Site Factory' => [
        'url' => 'site-factory',
        'alias' => ['site-factory', 'acsf'],
      ],
      'Site Studio' => [
        'url' => 'site-studio',
        'alias' => ['site-studio', 'cohesion'],
      ],
      'Edge' => [
        'url' => 'edge',
        'alias' => ['edge', 'cloudedge'],
      ],
      'Search' => [
        'url' => 'acquia-search',
        'alias' => ['search', 'acquia-search'],
      ],
      'Shield' => [
        'url' => 'shield',
        'alias' => ['shield'],
      ],
      'Customer Data Plateform' => [
        'url' => 'customer-data-platform',
        'alias' => ['customer-data-platform', 'cdp'],
      ],
      'Cloud IDE' => [
        'url' => 'ide',
        'alias' => ['ide', 'cloud_ide', 'cloud-ide'],
      ],
      'BLT' => [
        'url' => 'blt',
        'alias' => ['blt'],
      ],
      'Cloud Platform' => [
        'url' => 'cloud-platform',
        'alias' => ['cloud-platform', 'acquiacloud', 'acquia_cloud', 'acquia-cloud', 'cloud'],
      ],
      'Acquia DAM Classic' => [
        'url' => 'dam',
        'alias' => ['dam', 'acquia_dam', 'dam_classic', 'acquiadam', 'damclassic'],
      ],
      'Personalization' => [
        'url' => 'personalization',
        'alias' => ['personalization'],
      ],
      'Campaign Factory' => [
        'url' => 'campaign-factory',
        'alias' => ['campaign-factory', 'campaign_factory', 'campaignfactory'],
      ],
    ];

    // If user has provided any acquia product in command.
    if ($acquiaProductName = $input->getArgument('product')) {
      $product_url = NULL;
      foreach (array_values($acquia_products) as $acquia_product) {
        // If product provided by the user exists in the alias
        if (in_array(strtolower($acquiaProductName), $acquia_product['alias'])) {
          $product_url = $acquia_product['url'];
          break;
        }
      }

      if ($product_url) {
        $this->localMachineHelper->startBrowser('https://docs.acquia.com/' . $product_url . '/');
        return 0;
      }
    }

    $labels = array_keys($acquia_products);
    $question = new ChoiceQuestion('Please select the Acquia Product', $labels, $labels[0]);
    $choice_id = $this->io->askQuestion($question);
    $this->localMachineHelper->startBrowser('https://docs.acquia.com/' . $acquia_products[$choice_id]['url'] . '/');

    return 0;
  }

}
