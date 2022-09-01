<?php

namespace Acquia\Cli;

interface ApiCredentialsInterface {

  public function getBaseUri(): ?string;

  public function getCloudKey(): ?string;

  public function getCloudSecret(): ?string;

}
