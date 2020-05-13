<?php

namespace Acquia\Cli\Command;

use Exception;
use Phar;
use PharException;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;
use function error_reporting;

/**
 * Class UpdateCommand.
 */
class UpdateCommand extends CommandBase {

  protected $gitHubRepository;

  protected $applicationName;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('update')->setDescription('update to the latest version');
  }

  /**
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   * @throws \Exception
   * @throws \Exception
   * @throws \Exception
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->gitHubRepository = 'https://github.com/acquia/cli';

    if (empty(Phar::running())) {
      throw new RuntimeException('update only works when running the phar version of ' . $this->getApplication()
        ->getName() . '.');
    }

    // phpcs:ignore
    $localFilename = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
    $programName = basename($localFilename);
    $tempFilename = dirname($localFilename) . '/' . basename($localFilename, '.phar') . '-temp.phar';

    // Check for permissions in local filesystem before start connection process.
    if (!is_writable($tempDirectory = dirname($tempFilename))) {
      throw new RuntimeException($programName . ' update failed: the "' . $tempDirectory . '" directory used to download the temp file could not be written');
    }

    if (!is_writable($localFilename)) {
      throw new RuntimeException($programName . ' update failed: the "' . $localFilename . '" file could not be written (execute with sudo)');
    }

    [$latest, $downloadUrl] = $this->getLatestReleaseFromGithub();

    if ($this->getApplication()->getVersion() === $latest) {
      $output->writeln('No update available');

      return;
    }

    $fs = $this->getApplication()->getLocalMachineHelper()->getFilesystem();
    $output->writeln('Downloading ' . $this->getApplication()
      ->getName() . ' (' . $this->gitHubRepository . ') ' . $latest);
    $fs->copy($downloadUrl, $tempFilename);
    $output->writeln('Download finished');

    try {
      // Suppress notices.
      error_reporting(E_ALL);

      @chmod($tempFilename, 0777 & ~umask());
      // Test the phar validity.
      $phar = new Phar($tempFilename);
      // Free the variable to unlock the file.
      unset($phar);
      @rename($tempFilename, $localFilename);
      $output->writeln('<info>Successfully updated ' . $programName . '</info>');
      $this->_exit();
    }
    catch (Exception $e) {
      @unlink($tempFilename);
      if (!$e instanceof UnexpectedValueException && !$e instanceof PharException) {
        throw $e;
      }
      $output->writeln('<error>The download is corrupted (' . $e->getMessage() . ').</error>');
      $output->writeln('<error>Please re-run the self-update command to try again.</error>');
    }
  }

  /**
   *
   */
  protected function getLatestReleaseFromGithub(): array {
    $opts = [
      'http' => [
        'method' => 'GET',
        'header' => [
          'User-Agent: ' . $this->applicationName . ' (' . $this->gitHubRepository . ')' . ' Self-Update (PHP)',
        ],
      ],
    ];

    $context = stream_context_create($opts);
    $releases = file_get_contents(
          'https://api.github.com/repos/' . $this->gitHubRepository . '/releases',
          FALSE,
          $context
      );
    $releases = json_decode($releases);

    if (!isset($releases[0])) {
      throw new RuntimeException('API error - no release found at GitHub repository ' . $this->gitHubRepository);
    }

    $version = $releases[0]->tag_name;
    $url = $releases[0]->assets[0]->browser_download_url;

    return [$version, $url];
  }

  /**
   * Stop execution.
   *
   * This is a workaround to prevent warning of dispatcher after replacing
   * the phar file.
   *
   * @return void
   */
  protected function _exit(): void {
    exit;
  }

}
