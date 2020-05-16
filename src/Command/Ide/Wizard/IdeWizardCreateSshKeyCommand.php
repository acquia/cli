<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\EnvironmentResponse;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IdeWizardCreateSshKeyCommand.
 */
class IdeWizardCreateSshKeyCommand extends IdeWizardCommandBase {

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setName('ide:wizard:ssh-key:create-upload')
      ->setDescription('Wizard to perform first time setup tasks within an IDE')
      ->setHidden(!CommandBase::isAcquiaRemoteIde());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->requireRemoteIdeEnvironment();

    $ide_uuid = CommandBase::getThisRemoteIdeUuid();
    $cloud_app_uuid = $this->determineCloudApplication(TRUE);

    if ($this->userHasUploadedLocalKeyToCloud()) {
      throw new AcquiaCliException("You have already uploaded a local key to Acquia Cloud. You don't need to create a new one.");
    }

    $checklist = new Checklist($output);
    $checklist->addItem('Creating a local SSH key');

    // Create SSH key.
    $filename = $this->getSshKeyFilename($ide_uuid);
    $password = md5(random_bytes(10));

    $command = $this->getApplication()->find('ssh-key:create');
    $arguments = [
      'command' => $command->getName(),
      '--filename'  => $filename,
      '--password'  => $password,
    ];
    $create_input = new ArrayInput($arguments);
    $returnCode = $command->run($create_input, $output);
    if ($returnCode !== 0) {
      throw new AcquiaCliException('Unable to generate a local SSH key.');
    }
    $checklist->completePreviousItem();

    // Upload SSH key.
    $checklist->addItem('Uploading local key to Acquia Cloud');
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $ide = $ides_resource->get($ide_uuid);
    $ssh_key_label = $this->getIdeSshKeyLabel($ide);
    $filepath = $this->getApplication()->getSshKeysDir() . '/' . $filename . '.pub';
    $command = $this->getApplication()->find('ssh-key:upload');
    $arguments = [
      'command' => $command->getName(),
      '--label'  => $ssh_key_label,
      '--filepath' => $filepath,
      '--no-wait' => '',
    ];
    $upload_input = new ArrayInput($arguments);
    $returnCode = $command->run($upload_input, new NullOutput());
    if ($returnCode !== 0) {
      throw new AcquiaCliException('Unable to upload SSH key to Acquia Cloud');
    }
    $checklist->completePreviousItem();

    // Wait for SSH key to be available on a web.
    $dev_environment = $this->getDevEnvironment($cloud_app_uuid);
    // Wait for the key to register on Acquia Cloud.
    $this->pollAcquiaCloud($output, $dev_environment);
    // @todo Add to local ssh key agent.

    return 0;
  }

  /**
   * @return bool
   */
  protected function userHasUploadedLocalKeyToCloud(): bool {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $local_keys = $this->findLocalSshKeys();
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * @param string $cloud_app_uuid
   *
   * @return \AcquiaCloudApi\Response\EnvironmentResponse|null
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function getDevEnvironment($cloud_app_uuid): ?EnvironmentResponse {
    $acquia_cloud_client = $this->getApplication()->getAcquiaCloudClient();
    $environment_resource = new Environments($acquia_cloud_client);
    $application_environments = iterator_to_array($environment_resource->getAll($cloud_app_uuid));
    foreach ($application_environments as $environment) {
      if ($environment->name === 'dev') {
        return $environment;
      }
    }
    return NULL;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \AcquiaCloudApi\Response\EnvironmentResponse $environment
   */
  protected function pollAcquiaCloud(
    OutputInterface $output,
    EnvironmentResponse $environment
  ): void {
    // Create a loop to periodically poll Acquia Cloud.
    $loop = Factory::create();
    $spinner = $this->addSpinnerToLoop($loop,
      'Waiting for key to become available on Acquia Cloud web servers...');

    // Poll Cloud every 5 seconds.
    $loop->addPeriodicTimer(5, function () use ($output, $loop, $environment, $spinner) {
      $process = $this->getApplication()->getSshHelper()->executeCommand($environment, ['ls']);
      if ($process->isSuccessful()) {
        $this->finishSpinner($spinner);
        $output->writeln("\n<info>Your SSH key is ready for use.</info>");
        $loop->stop();
      }
    });
    $this->addTimeoutToLoop($loop, 10, $spinner);
    $loop->run();
  }

}
