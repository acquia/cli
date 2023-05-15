<?php

namespace Acquia\Cli\DataStore;

use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\Helpers\LocalMachineHelper;

class CloudDataStore extends JsonDataStore {

  protected array $config;

  public function __construct(
    protected LocalMachineHelper $localMachineHelper,
    CloudDataConfig $cloudDataConfig,
    string $cloudConfigFilepath
  ) {
    parent::__construct($cloudConfigFilepath, $cloudDataConfig);
  }

}
