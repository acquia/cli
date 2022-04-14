<?php

namespace Acquia\Cli;

interface CommandFactoryInterface {

  public function createCommand();

  public function createListCommand();

}
