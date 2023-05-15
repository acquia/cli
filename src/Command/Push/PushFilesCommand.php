<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushFilesCommand extends PullCommandBase {

  protected static $defaultName = 'push:files';

  protected function configure(): void {
    $this->setDescription('Push Drupal files from your IDE to a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->acceptSite()
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
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
    $answer = $this->io->confirm("Overwrite the public files directory on <bg=cyan;options=bold>{$destinationEnvironment->name}</> with a copy of the files from the current machine?");
    if (!$answer) {
      return 0;
    }

    $this->checklist = new Checklist($output);
    $this->checklist->addItem('Pushing public files directory to remote machine');
    $this->rsyncFilesToCloud($destinationEnvironment, $this->getOutputCallback($output, $this->checklist), $chosenSite);
    $this->checklist->completePreviousItem();

    return 0;
  }

  /**
   * @param $chosenEnvironment
   * @param callable|null $outputCallback
   * @param string|null $site
   */
  private function rsyncFilesToCloud($chosenEnvironment, callable $outputCallback = NULL, string $site = NULL): void {
    $source = $this->dir . '/docroot/sites/default/files/';
    $sitegroup = self::getSiteGroupFromSshUrl($chosenEnvironment->sshUrl);

    if ($this->isAcsfEnv($chosenEnvironment)) {
      $destDir = '/mnt/files/' . $sitegroup . '.' . $chosenEnvironment->name . '/sites/g/files/' . $site . '/files';
    }
    else {
      $destDir = '/mnt/files/' . $sitegroup . '.' . $chosenEnvironment->name . '/sites/' . $site . '/files';
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
    $command = [
      'rsync',
      // -a archive mode; same as -rlptgoD.
      // -z compress file data during the transfer.
      // -v increase verbosity.
      // -P show progress during transfer.
      // -h output numbers in a human-readable format.
      // -e specify the remote shell to use.
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $source,
      $chosenEnvironment->sshUrl . ':' . $destDir,
    ];
    $process = $this->localMachineHelper->execute($command, $outputCallback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to sync files to Cloud. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

}
