<?php

namespace Acquia\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Webmozart\PathUtil\Path;

/**
 * Class NewCommand.
 */
class NewCommand extends CommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('new')
      ->setDescription('Create a new Drupal project')
      ->addOption('distribution', NULL, InputOption::VALUE_REQUIRED, '');
    // @todo Add argument to set destination directory.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $distros = [
      'acquia/blt-project',
      'acquia/lightning-project',
      'drupal/recommended-project',
    ];
    $question = new ChoiceQuestion('<question>Which starting project would you like to use?</question>', $distros);
    $helper = $this->getHelper('question');
    $project = $helper->ask($this->input, $this->output, $question);

    $dir = Path::join(getcwd(), 'drupal');
    $filepath = Path::join($dir, 'composer.json');

    $this->createProject($project, $dir);

    if (strpos($project, 'drupal/recommended-project') !== FALSE) {
      $this->replaceWebRoot($filepath);
      $this->requireDrush($dir);
    }

    // We've deferred all installation until now.
    $this->localMachineHelper->execute([
      'composer',
      'update',
    ], NULL, $dir);

    // @todo Add a .gitignore and other recommended default files.
    $this->initializeGitRepository($dir);

    $output->writeln('');
    $output->writeln("<info>New 💧Drupal project created in $dir. 🎉");

    return 0;
  }

  /**
   * @return bool
   */
  protected function commandRequiresAuthentication(): bool {
    return FALSE;
  }

  /**
   * @param string $filepath
   */
  protected function replaceWebRoot(string $filepath): void {
    $contents = file_get_contents($filepath);
    $contents = str_replace('web/', 'docroot/', $contents);
    file_put_contents($filepath, $contents);
  }

  /**
   * @param string $dir
   *
   * @throws \Exception
   */
  protected function requireDrush(string $dir): void {
    $this->localMachineHelper->execute([
      'composer',
      'require',
      'drush/drush',
      '--no-update',
    ], NULL, $dir);
  }

  /**
   * @param $project
   * @param string $dir
   *
   * @throws \Exception
   */
  protected function createProject($project, string $dir): void {
    $this->localMachineHelper->execute([
      'composer',
      'create-project',
      '--no-install',
      $project,
      $dir,
    ]);
    // @todo Check that this was successful!
  }

  /**
   * @param string $dir
   *
   * @throws \Exception
   */
  protected function initializeGitRepository(string $dir): void {
    $this->localMachineHelper->execute([
      'git',
      'init',
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
      'Initial commit.',
      '--quiet',
    ], NULL, $dir);
  }

}
