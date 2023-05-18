<?php

namespace Acquia\Cli\Command\Ssh;

use AcquiaCloudApi\Endpoints\SshKeys;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyListCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:list';

  protected function configure(): void {
    $this->setDescription('List your local and remote SSH keys');
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $acquiaCloudClient = $this->cloudApiClientService->getClient();
    $sshKeys = new SshKeys($acquiaCloudClient);
    $cloudKeys = $sshKeys->getAll();
    $localKeys = $this->findLocalSshKeys();
    $table = $this->createSshKeyTable($output, 'Cloud Platform keys with matching local keys');
    foreach ($localKeys as $localIndex => $localFile) {
      /** @var \AcquiaCloudApi\Response\SshKeyResponse $cloudKey */
      foreach ($cloudKeys as $index => $cloudKey) {
        if (trim($localFile->getContents()) === trim($cloudKey->public_key)) {
          $hash = self::getFingerprint($cloudKey->public_key);
          $table->addRow([
            $cloudKey->label,
            $localFile->getFilename(),
            $hash,
          ]);
          unset($cloudKeys[$index], $localKeys[$localIndex]);
          break;
        }
      }
    }
    $table->render();
    $this->io->newLine();

    $table = $this->createSshKeyTable($output, 'Cloud Platform keys with no matching local keys');
    foreach ($cloudKeys as $cloudKey) {
      $hash = self::getFingerprint($cloudKey->public_key);
      $table->addRow([
        $cloudKey->label,
        'none',
        $hash,
      ]);
    }
    $table->render();
    $this->io->newLine();

    $table = $this->createSshKeyTable($output, 'Local keys with no matching Cloud Platform keys');
    foreach ($localKeys as $localFile) {
      $hash = self::getFingerprint($localFile->getContents());
      $table->addRow([
        'none',
        $localFile->getFilename(),
        $hash,
      ]);
    }
    $table->render();

    return 0;
  }

  private function createSshKeyTable(OutputInterface $output, string $title): Table {
    $headers = ['Cloud Platform label', 'Local filename', 'Fingerprint (sha256)'];
    $widths = [.4, .2, .2];
    return $this->createTable($output, $title, $headers, $widths);
  }

}
