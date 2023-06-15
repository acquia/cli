<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\JsonResourceParserTrait;
use Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension;
use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorBase;

final class ExportedDrupal7ExtensionsInspector extends SiteInspectorBase {

  use JsonResourceParserTrait;

  /**
   * ExportedDrupal7ExtensionsInspector constructor.
   *
   * @param \Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension[] $extensions
   *   An array of extensions.
   */
  protected function __construct(
    protected array $extensions
  ) {}

  /**
   * Creates a new ExportedDrupal7ExtensionsInspector.
   *
   * @param resource $extensions_resource
   *   A resource containing a list of Drupal 7 extension information.
   * @return static
   *   A new instance of this class.
   */
  public static function createFromResource($extensions_resource): static {
    assert(is_resource($extensions_resource));
    return new static(static::parseExtensionsFromResource($extensions_resource));
  }

  /**
   * {@inheritDoc}
   */
  protected function readExtensions(): array {
    return $this->extensions;
  }

  public function getPublicFilePath(): string {
    return 'sites/default/files';
  }

  public function getPrivateFilePath(): ?string {
    return NULL;
  }

  /**
   * Reads an extensions resource into extensions objects.
   *
   * @param resource $extensions_resource
   *   A serialized extensions resource from which to parse extensions.
   * @return \Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension[]
   *   An array of extensions.
   */
  protected static function parseExtensionsFromResource($extensions_resource): array {
    return array_map(function (array $extension) {
      $extension['status'] = $extension['enabled'];
      return Drupal7Extension::createFromStdClass((object) $extension);
    }, static::parseJsonResource($extensions_resource));
  }

}
