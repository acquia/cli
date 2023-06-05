<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Response\EnvironmentResponse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushFilesCommand extends PullCommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'push:files';

  protected function configure(): void {
    $this->setDescription('Push Drupal files from your local environment to a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->acceptSite();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->setDirAndRequireProjectCwd($input);
    $destinationEnvironment = $this->determineEnvironment($input, $output);
    $chosenSite = $input->getArgument('site');
    if (!$chosenSite) {
      if ($this->isAcsfEnv($destinationEnvironment)) {
        $chosenSite = $this->promptChooseAcsfSite($destinationEnvironment);
      }
      else {
        $chosenSite = $this->promptChooseCloudSite($destinationEnvironment);
      }
    }
    $answer = $this->io->confirm("Overwrite the public files directory on <bg=cyan;options=bold>$destinationEnvironment->name</> with a copy of the files from the current machine?");
    if (!$answer) {
      return Command::SUCCESS;
    }

    $this->checklist = new Checklist($output);
    $this->checklist->addItem('Pushing public files directory to remote machine');
    $this->rsyncFilesToCloud($destinationEnvironment, $this->getOutputCallback($output, $this->checklist), $chosenSite);
    $this->checklist->completePreviousItem();

    return Command::SUCCESS;
  }

  private function rsyncFilesToCloud(EnvironmentResponse $chosenEnvironment, callable $outputCallback = NULL, string $site = NULL): void {
    $sourceDir = $this->getLocalFilesDir($site);
    $destinationDir = $chosenEnvironment->sshUrl . ':' . $this->getCloudFilesDir($chosenEnvironment, $site);

    $this->rsyncFiles($sourceDir, $destinationDir, $outputCallback);
  }

}
