<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App;

use Acquia\Cli\Command\App\From\Composer\ProjectBuilder;
use Acquia\Cli\Command\App\From\Configuration;
use Acquia\Cli\Command\App\From\Recommendation\Recommendations;
use Acquia\Cli\Command\App\From\Recommendation\Resolver;
use Acquia\Cli\Command\App\From\SourceSite\Drupal7SiteInspector;
use Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface;
use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface;
use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Exception\ValidatorException;

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

  /**
   * @see \Acquia\Cli\Tests\Commands\App\NewFromDrupal7CommandTest::testNewDrupalCommand()
   */
  private ?SiteInspectorInterface $overriddenInspector = NULL;

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'app:new:from:drupal7';

  protected function configure(): void {
    $this->setDescription('Generate a new Drupal 9+ project from a Drupal 7 application using the default Acquia Migrate Accelerate recommendations.')
      ->addOption('drupal7-directory', 'source', InputOption::VALUE_OPTIONAL, 'The root of the Drupal 7 application.')
      ->addOption('drupal7-uri', 'uri', InputOption::VALUE_OPTIONAL, 'Only necessary in case of a multisite. If a single site, this will be computed automatically.')
      ->addOption('recommendations', 'recommendations', InputOption::VALUE_OPTIONAL, 'Overrides the default recommendations.')
      ->addOption('directory', 'destination', InputOption::VALUE_OPTIONAL, 'The directory where to generate the new application.')
      ->setAliases([
        // Currently only "from Drupal 7", more to potentially follow.
        'from:d7',
        // A nod to its roots.
        'ama',
      ]);
  }

  /**
   * Validates the given Drupal 7 application root.
   *
   * @param string $path
   *   The path to validate.
   * @return string
   *   The received Drupal 7 path, if it is valid, without trailing slashes.
   */
  public static function validateDrupal7Root(string $path): string {
    $path = rtrim($path, '/');
    if (!file_exists($path)) {
      throw new ValidatorException(sprintf("The path '%s' does not exist. Please enter the absolute path to a Drupal 7 application root.", $path));
    }
    if (!file_exists("$path/index.php")) {
      throw new ValidatorException(sprintf("The '%s' directory does not seem to be the root of a Drupal 7 application. It does not contain a index.php file.", $path));
    }
    if (!file_exists("$path/sites/default/default.settings.php")) {
      throw new ValidatorException(sprintf("The '%s' directory does not seem to be the root of a Drupal 7 application. It does not contain a sites/default/default.settings.php.", $path));
    }
    return $path;
  }

  /**
   * Determines the best URI to use for bootstrapping the source site.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input passed into this Symfony command.
   * @param string $drupal_root
   *   The root of the source site.
   * @return string
   *   A URI string corresponding to an installed site.
   */
  private static function getSiteUri(InputInterface $input, string $drupal_root): string {
    // Construct a list of site directories which contain a settings.php file.
    $site_dirs = array_map(function ($path) use ($drupal_root) {
      return substr($path, strlen("$drupal_root/sites/"), -1 * strlen('/settings.php'));
    }, glob("$drupal_root/sites/*/settings.php"));
    // If the --drupal7-uri flag is defined, defer to it and attempt to ensure that it's
    // valid.
    if ($input->hasOption('drupal7-uri')) {
      $uri = $input->getOption('drupal7-uri');
      $sites_location = "$drupal_root/sites/sites.php";
      // If there isn't a sites.php file and the URI does not correspond to a
      // site directory, the site will be unable to bootstrap.
      if (!file_exists($sites_location) && !in_array($uri, $site_dirs, TRUE)) {
        throw new \InvalidArgumentException(
          sprintf('The given --drupal7-uri value does not correspond to an installed sites directory and a sites.php file could not be located.'),
          static::ERR_UNRECOGNIZED_HOST
        );
      }
      // Parse the contents of sites.php.
      $sites = [];
      // This will override $sites.
      // @see https://git.drupalcode.org/project/drupal/-/blob/7.x/includes/bootstrap.inc#L563
      include $sites_location;

      // @phpstan-ignore-next-line
      if (!empty($sites)) {
        // If the URI corresponds to a configuration in sites.php, then ensure
        // that the identified directory also has a settings.php file. If it
        // does not, then the site is probably not installed.
        if (isset($sites[$uri])) {
          if (!in_array($sites[$uri], $site_dirs, TRUE)) {
            throw new \InvalidArgumentException(
              sprintf('The given --drupal7-uri value corresponds to a site directory in sites.php, but that directory does not have a settings.php file. This typically means that the site has not been installed.'),
              static::ERR_UNRECOGNIZED_HOST
            );
          }
          // The URI is assumed to be valid.
          return $uri;
        }
        // The given URI doesn't match anything in sites.php.
        throw new \InvalidArgumentException(
          sprintf('The given --drupal7-uri value does not correspond to any configuration in sites.php.'),
          static::ERR_UNRECOGNIZED_HOST
        );
      }

      if (in_array($uri, $site_dirs, TRUE)) {
        return $uri;
      }
    }
    // There was no --drupal7-uri flag specified, so attempt to determine a sane
    // default. If there is only one possible site, use it. If there is more
    // than one, but there is a default directory with a settings.php, use that.
    if (count($site_dirs) === 1) {
      return current($site_dirs);
    }
    elseif (in_array('default', $site_dirs, TRUE)) {
      return 'default';
    }
    // A URI corresponding to a site directory could not be determined, rather
    // than make a faulty assumption (e.g. use the first found), exit.
    throw new \InvalidArgumentException(
      sprintf('A Drupal 7 installation could not be located.'),
      static::ERR_INDETERMINATE_SITE
    );
  }

  private function getInspector(InputInterface $input): SiteInspectorInterface {
    // Allow inspector to be overridden for testing using reflection.
    if ($this->overriddenInspector) {
      return $this->overriddenInspector;
    }

    // First: Determine the Drupal 7 root.
    if ($input->getOption('drupal7-directory') === NULL) {
      $answer = $this->io->ask(
        'What is the root of the Drupal 7 application you want to generate a new Drupal project for?',
        NULL,
        [static::class, 'validateDrupal7Root'],
      );
      $input->setOption('drupal7-directory', $answer);
    }
    $d7_root = $input->getOption('drupal7-directory');

    // Second, determine which "sites" subdirectory is being assessed.
    $uri = static::getSiteUri($input, $d7_root);

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
      if (strpos($location, '.') === 0 || strpos($location, '/') !== 0) {
        $absolute = getcwd() . '/' . $location;
        $location = $should_exist ? realpath($absolute) : $absolute;
      }
    }
    return $location;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    try {
      $inspector = $this->getInspector($input);
    }
    catch (\InvalidArgumentException $e) {
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
    $recommendations_location = __DIR__ . '/../../../config/from_d7_recommendations.json';
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
