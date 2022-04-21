<?php

namespace Acquia\Cli\DataStore;

use Acquia\Cli\Config\AcquiaCliConfig;
use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\Helpers\LocalMachineHelper;

class AcquiaCliDatastore extends YamlStore implements DataStoreInterface {

  /**
   * @var \Acquia\Cli\Helpers\LocalMachineHelper
   */
  protected LocalMachineHelper $localMachineHelper;

  /**
   * @var array
   */
  protected array $config;

  /**
   * @param \Acquia\Cli\Helpers\LocalMachineHelper $local_machine_helper
   * @param \Acquia\Cli\Config\AcquiaCliConfig $config_definition
   * @param string $acliConfigFilepath
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