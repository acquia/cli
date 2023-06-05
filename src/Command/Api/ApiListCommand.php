<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Api;

class ApiListCommand extends ApiListCommandBase {

  /**
   * @var string
   * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
   */
  protected static $defaultName = 'api:list';
  protected string $namespace = 'api';

  protected function configure(): void {
    $this->setDescription("List all API commands")
      ->setAliases(['api']);
  }

}
