<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\App\From\Composer\ProjectBuilder;
use Acquia\Cli\Command\App\From\Configuration;
use Acquia\Cli\Command\App\From\Recommendation\Recommendations;
use Acquia\Cli\Command\App\From\Recommendation\Resolver;
use Acquia\Cli\Command\App\From\SourceSite\Drupal7SiteInspector;
use Acquia\Cli\Command\App\From\SourceSite\ExportedDrupal7ExtensionsInspector;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Exception\ValidatorException;

#[AsCommand(name: 'app:new:from:drupal7')]
class NewFromDrupal7Command extends CommandBase {

  /**
   * Exit code raised when the URI flag does not correspond to configuration.
   *
   * This typically indicates the value of the --drupal7-uri flag does not
   * correspond to any configuration in a Drupal site's sites/sites.php file.
   */
  public const ERR_UNRECOGNIZED_HOST = 3;

  /**
   * Exit code raised when a Drupal 7 installation cannot be determined.
   *
   * This indicates the --drupal7-uri was not given and a sane default site could not be
   * determined.
   */
  public const ERR_INDETERMINATE_SITE = 4;

  protected function configure(): void {
    $this->setDescription('Generate a new Drupal 9+ project from a Drupal 7 application using the default Acquia Migrate Accelerate recommendations.')
      ->addOption('drupal7-directory', 'source', InputOption::VALUE_OPTIONAL, 'The root of the Drupal 7 application.')
      ->addOption('drupal7-uri', 'uri', InputOption::VALUE_OPTIONAL, 'Only necessary in case of a multisite. If a single site, this will be computed automatically.')
      ->addOption('stored-analysis', 'analysis', InputOption::VALUE_OPTIONAL, 'As an alternative to drupal7-directory, it is possible to pass a stored analysis.')
      ->addOption('recommendations', 'recommendations', InputOption::VALUE_OPTIONAL, 'Overrides the default recommendations.')
      ->addOption('directory', 'destination', InputOption::VALUE_OPTIONAL, 'The directory where to generate the new application.')
      ->setAliases([
        // Currently only "from Drupal 7", more to potentially follow.
        'from:d7',
        // A nod to its roots.
        'ama',
      ]);
  }

  private function getInspector(InputInterface $input): SiteInspectorInterface {
    if ($input->getOption('stored-analysis') !== NULL) {
      $analysis_json = $input->getOption('stored-analysis');
      $extensions_resource = fopen($analysis_json, 'r');
      $inspector = ExportedDrupal7ExtensionsInspector::createFromResource($extensions_resource);
      fclose($extensions_resource);
      return $inspector;
    }

    // First: Determine the Drupal 7 root.
    if ($input->getOption('drupal7-directory') === NULL) {
      $answer = $this->io->ask(
        'What is the root of the Drupal 7 application you want to generate a new Drupal project for?',
        NULL,
        [Drupal7SiteInspector::class, 'validateDrupal7Root'],
      );
      $input->setOption('drupal7-directory', $answer);
    }
    $d7_root = $input->getOption('drupal7-directory');

    // Second, determine which "sites" subdirectory is being assessed.
    $uri = Drupal7SiteInspector::getSiteUri($input, $d7_root);

    return new Drupal7SiteInspector($d7_root, $uri);
  }

  private function getLocation(string $location, bool $should_exist = TRUE): string {
    if (strpos($location, '://') === FALSE) {
      $file_exists = file_exists($location);
      if ($file_exists && !$should_exist) {
        throw new ValidatorException(sprintf('The %s directory already exists.', $location));
      }
      elseif (!$file_exists && $should_exist) {
        throw new ValidatorException(sprintf('%s could not be located. Check that the path is correct and try again.', $location));
      }
      if (strpos($location, '.') === 0 || !static::isAbsolutePath($location)) {
        $absolute = getcwd() . '/' . $location;
        $location = $should_exist ? realpath($absolute) : $absolute;
      }
    }
    return $location;
  }

  private static function isAbsolutePath(string $path): bool {
    // @see https://stackoverflow.com/a/23570509
    return $path[0] === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i', $path) > 0;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $inspector = $this->getInspector($input);
    }
    catch (\Exception $e) {
      $this->io->error($e->getMessage());
      // Important: ensure that the unique error code that ::getSiteUri()
      // computed is passed on, to enable scripting this command.
      return $e->getCode();
    }

    // Now the Drupal 7 site can be inspected. Inform the user.
    $output->writeln('<info>🤖 Scanning Drupal 7 site.</info>');
    $extensions = $inspector->getExtensions(SiteInspectorInterface::FLAG_EXTENSION_MODULE | SiteInspectorInterface::FLAG_EXTENSION_ENABLED);
    $module_count = count($extensions);
    $system_module_version = array_reduce(
      array_filter($extensions, fn (ExtensionInterface $extension) => $extension->isModule() && $extension->getName() === 'system'),
      fn (mixed $carry, ExtensionInterface $extension) => $extension->getVersion()
    );
    $site_location = property_exists($inspector, 'uri') ? 'sites/' . $inspector->uri : '<location unknown>';
    $output->writeln(sprintf("<info>👍 Found Drupal 7 site (%s to be precise) at %s, with %d modules enabled!</info>", $system_module_version, $site_location, $module_count));

    // Parse config for project builder.
    $configuration_location = __DIR__ . '/../../../config/from_d7_config.json';
    $config_resource = fopen($configuration_location, 'r');
    $configuration = Configuration::createFromResource($config_resource);
    fclose($config_resource);

    // Parse recommendations for project builder.
    $recommendations_location = "https://git.drupalcode.org/project/acquia_migrate/-/raw/recommendations/recommendations.json";
    if ($input->getOption('recommendations') !== NULL) {
      $raw_recommendations_location = $input->getOption('recommendations');
      try {
        $recommendations_location = $this->getLocation($raw_recommendations_location);
      }
      catch (\InvalidArgumentException $e) {
        $this->io->error($e->getMessage());
        return Command::FAILURE;
      }
    }
    // PHP defaults to no user agent. (Drupal.org's) GitLab requires it.
    // @see https://www.php.net/manual/en/filesystem.configuration.php#ini.user-agent
    ini_set('user_agent', 'ACLI');
    $recommendations_resource = fopen($recommendations_location, 'r');
    $recommendations = Recommendations::createFromResource($recommendations_resource);
    fclose($recommendations_resource);

    // Build project (in memory) using the configuration and the given
    // recommendations from the inspected Drupal 7 site and inform the user.
    $output->writeln('<info>🤖 Computing recommendations for this Drupal 7 site…</info>');
    $project_builder = new ProjectBuilder($configuration, new Resolver($inspector, $recommendations), $inspector);
    $results = $project_builder->buildProject();
    $unique_patch_count = array_reduce(
      $results['rootPackageDefinition']['extra']['patches'],
      fn (array $unique_patches, array $patches) => array_unique(array_merge($unique_patches, array_values($patches))),
      []
    );
    $output->writeln(sprintf(
      "<info>🥳 Great news: found %d recommendations that apply to this Drupal 7 site, resulting in a composer.json with:\n\t- %d packages\n\t- %d patches\n\t- %d modules to be installed!</info>",
      count($results['recommendations']),
      count($results['rootPackageDefinition']['require']),
      $unique_patch_count,
      count($results['installModules']),
    ));

    // Ask where to store the generated project (in other words: where to write
    // a composer.json file). If a directory path is passed, assume the user
    // knows what they're doing.
    if ($input->getOption('directory') === NULL) {
      $answer = $this->io->ask(
        'Where should the generated composer.json be written?',
        NULL,
        function (mixed $path): string {
          if (!is_string($path) || !file_exists($path) || file_exists("$path/composer.json")) {
            throw new ValidatorException(sprintf("The '%s' directory either does not exist or it already contains a composer.json file.", $path));
          }
          return $path;
        },
      );
      $input->setOption('directory', $answer);
    }
    $dir = $input->getOption('directory');

    // Create the info metadata array, including a complete root. Write this to
    // a metadata JSON file in the given directory. Also generate a
    // composer.json from this. Initialize a new Git repo and commit both.
    $data = array_merge(
      ['generated' => date(DATE_ATOM)],
      $project_builder->buildProject()
    );
    $json_encode_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
    file_put_contents("$dir/acli-generated-project-metadata.json", json_encode($data, $json_encode_flags));
    file_put_contents("$dir/composer.json", json_encode($data['rootPackageDefinition'], $json_encode_flags));
    $this->initializeGitRepository($dir);
    $output->writeln('<info>🚀 Generated composer.json and committed to a new git repo.</info>');
    $output->writeln('');

    // Helpfully automatically run `composer install`, but equally helpfully do
    // not commit it yet, to allow the user to choose whether to commit build
    // artifacts.
    $output->writeln('<info>⏳ Installing. This may take a few minutes.</info>');
    $this->localMachineHelper->checkRequiredBinariesExist(['composer']);
    $process = $this->localMachineHelper->execute([
      'composer',
      'install',
      '--working-dir',
      $dir,
      '--no-interaction',
    ]);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException("Unable to create new project.");
    }

    $output->writeln('');
    $output->writeln("<info>New 💧 Drupal project created in $dir. 🎉</info>");

    return Command::SUCCESS;
  }

  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  private function initializeGitRepository(string $dir): void {
    if ($this->localMachineHelper->getFilesystem()->exists(Path::join($dir, '.git'))) {
      $this->logger->debug('.git directory detected, skipping Git repo initialization');
      return;
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['git']);
    $this->localMachineHelper->execute([
      'git',
      'init',
      '--initial-branch=main',
      '--quiet',
    ], NULL, $dir);

    $this->localMachineHelper->execute([
      'git',
      'add',
      '-A',
    ], NULL, $dir);

    $this->localMachineHelper->execute([
      'git',
      'commit',
      '--message',
      "Generated by Acquia CLI's app:new:from:drupal7.",
      '--quiet',
    ], NULL, $dir);
    // @todo Check that this was successful!
  }

}
