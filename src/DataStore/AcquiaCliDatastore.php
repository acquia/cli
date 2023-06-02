<?php

namespace Acquia\Cli\DataStore;

use Acquia\Cli\Config\AcquiaCliConfig;
use Acquia\Cli\Helpers\LocalMachineHelper;

class AcquiaCliDatastore extends YamlStore {

  /**
   * @var array<mixed>
   */
  protected array $config;

  public function __construct(
    protected LocalMachineHelper $localMachineHelper,
    AcquiaCliConfig $configDefinition,
    string $acliConfigFilepath
  ) {
    $filePath = $localMachineHelper->getLocalFilepath($acliConfigFilepath);
    parent::__construct($filePath, $configDefinition);
  }

}
