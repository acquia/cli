<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(name: 'docs')]
class DocsCommand extends CommandBase {

  protected function configure(): void {
    $this->setDescription('Open Acquia product documentation in a web browser')
      ->addArgument('product', InputArgument::OPTIONAL, 'Acquia Product Name')
      ->addUsage('acli');
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaProducts = [
      'Acquia CLI' => [
        'alias' => ['cli', 'acli'],
        'url' => 'acquia-cli',
      ],
      'Acquia CMS' => [
        'alias' => ['acquia_cms', 'acms'],
        'url' => 'acquia-cms',
      ],
      'Acquia DAM Classic' => [
        'alias' => ['dam', 'acquia_dam', 'dam_classic', 'acquiadam', 'damclassic'],
        'url' => 'dam',
      ],
      'Acquia Migrate Accelerate' => [
        'alias' => ['acquia-migrate-accelerate', 'ama'],
        'url' => 'acquia-migrate-accelerate',
      ],
      'BLT' => [
        'alias' => ['blt'],
        'url' => 'blt',
      ],
      'Campaign Factory' => [
        'alias' => ['campaign-factory', 'campaign_factory', 'campaignfactory'],
        'url' => 'campaign-factory',
      ],
      'Campaign Studio' => [
        'alias' => ['campaign-studio', 'campaignstudio'],
        'url' => 'campaign-studio',
      ],
      'Cloud IDE' => [
        'alias' => ['ide', 'cloud_ide', 'cloud-ide'],
        'url' => 'ide',
      ],
      'Cloud Platform' => [
        'alias' => ['cloud-platform', 'acquiacloud', 'acquia_cloud', 'acquia-cloud', 'cloud'],
        'url' => 'cloud-platform',
      ],
      'Code Studio' => [
        'alias' => ['code_studio', 'codestudio', 'cs'],
        'url' => 'code-studio',
      ],
      'Content Hub' => [
        'alias' => ['contenthub', 'ch'],
        'url' => 'contenthub',
      ],
      'Customer Data Platform' => [
        'alias' => ['customer-data-platform', 'cdp'],
        'url' => 'customer-data-platform',
      ],
      'Edge' => [
        'alias' => ['edge', 'cloudedge'],
        'url' => 'edge',
      ],
      'Personalization' => [
        'alias' => ['personalization'],
        'url' => 'personalization',
      ],
      'Search' => [
        'alias' => ['search', 'acquia-search'],
        'url' => 'acquia-search',
      ],
      'Shield' => [
        'alias' => ['shield'],
        'url' => 'shield',
      ],
      'Site Factory' => [
        'alias' => ['site-factory', 'acsf'],
        'url' => 'site-factory',
      ],
      'Site Studio' => [
        'alias' => ['site-studio', 'cohesion'],
        'url' => 'site-studio',
      ],
    ];

    // If user has provided any acquia product in command.
    if ($acquiaProductName = $input->getArgument('product')) {
      $productUrl = NULL;
      foreach ($acquiaProducts as $acquiaProduct) {
        // If product provided by the user exists in the alias
        if (in_array(strtolower($acquiaProductName), $acquiaProduct['alias'], TRUE)) {
          $productUrl = $acquiaProduct['url'];
          break;
        }
      }

      if ($productUrl) {
        $this->localMachineHelper->startBrowser('https://docs.acquia.com/' . $productUrl . '/');
        return Command::SUCCESS;
      }
    }

    $labels = array_keys($acquiaProducts);
    $question = new ChoiceQuestion('Select the Acquia Product', $labels, $labels[0]);
    $choiceId = $this->io->askQuestion($question);
    $this->localMachineHelper->startBrowser('https://docs.acquia.com/' . $acquiaProducts[$choiceId]['url'] . '/');

    return Command::SUCCESS;
  }

}
