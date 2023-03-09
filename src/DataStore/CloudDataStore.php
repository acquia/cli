<?php

namespace Acquia\Cli\DataStore;

use Acquia\Cli\Config\CloudDataConfig;
use Acquia\Cli\Helpers\LocalMachineHelper;

class CloudDataStore extends JsonDataStore {

  protected LocalMachineHelper $localMachineHelper;

  /**
   * @var array
   */
  protected array $config;

  /**
   *
   * @throws \JsonException
   * @throws \JsonException
   */
  public function __construct(
    LocalMachineHelper $local_machine_helper,
    CloudDataConfig $cloud_data_config,
    string $cloudConfigFilepath
  ) {
    $this->localMachineHelper = $local_machine_helper;
    parent::__construct($cloudConfigFilepath, $cloud_data_config);
  }

}
