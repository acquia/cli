<?php

namespace Acquia\Cli\Command\DrupalUpdate;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatedPackagesInfo{
  /**
   * @var OutputInterface
   */
  private OutputInterface $output;

  public function __construct(OutputInterface $output) {
    $this->output = $output;
  }

  /**
   * @param $version_detail
   */
  public function printPackageDetail($version_detail) {
    $table = new Table($this->output);
    $git_commit_message_detail=[];

    array_shift($version_detail);
    $array_keys = array_column($version_detail, 'package');
    array_multisort($array_keys, SORT_ASC, $version_detail);

    foreach ($version_detail as $versions) {
      $package = $versions['package'];
      $git_commit_message=[];
      $git_commit_message[] = $package;
      $git_commit_message[] = $versions['package_type'];
      $git_commit_message[] = isset($versions['current_version']) ? $versions['current_version'] : '';
      $git_commit_message[] = isset($versions['latest_version']) ? $versions['latest_version'] : '';
      $git_commit_message[] = isset($versions['update_notes']) ? $versions['update_notes'] : '';
      $git_commit_message_detail[] = $git_commit_message;
    }
    $table->setHeaders([
          'Package Name',
          'Package Type',
          'Current Version',
          'Latest Version',
          'Update Type'
      ])->setRows($git_commit_message_detail);
    $table->render();
  }

}
