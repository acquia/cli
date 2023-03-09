<?php

namespace Acquia\Cli\DataStore;

use Acquia\Cli\Config\AcquiaCliConfig;
use Acquia\Cli\Helpers\LocalMachineHelper;

class AcquiaCliDatastore extends YamlStore {

  protected LocalMachineHelper $localMachineHelper;

  /**
   * @var array
   */
  protected array $config;

  /**
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  public function __construct(
    LocalMachineHelper $local_machine_helper,
    AcquiaCliConfig $config_definition,
    string $acliConfigFilepath
  ) {
    $this->localMachineHelper = $local_machine_helper;
    $file_path = $local_machine_helper->getLocalFilepath($acliConfigFilepath);
    parent::__construct($file_path, $config_definition);
  }

}
