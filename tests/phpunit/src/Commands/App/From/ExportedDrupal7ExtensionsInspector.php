<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\JsonResourceParserTrait;
use Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension;
use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorBase;

/**
 * Class ExportedDrupal7ExtensionsInspector.
 *
 * @internal
 */
class ExportedDrupal7ExtensionsInspector extends SiteInspectorBase {

  use JsonResourceParserTrait;

  /**
   * ExportedDrupal7ExtensionsInspector constructor.
   *
   * @param \Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension[] $extensions
   *   An array of Drupal 7 extensions.
   */
  protected function __construct(
    protected array $extensions
  ) {}

  /**
   * Creates a new ExportedDrupal7ExtensionsInspector.
   *
   * @param $extensions_resource
   *   A resource containing a list of Drupal 7 extension information.
   *
   * @return static
   *   A new instance of this class.
   *
   * @throws \JsonException
   *   Thrown if the given resource contains malformed JSON.
   */
  public static function createFromResource($extensions_resource) {
    assert(is_resource($extensions_resource));
    return new static(static::parseExtensionsFromResource($extensions_resource));
  }

  /**
   * {@inheritDoc}
   */
  protected function readExtensions(): array {
    return $this->extensions;
  }

  /**
   * {@inheritDoc}
   */
  public function getPublicFilePath(): string {
    return 'sites/default/files';
  }

  /**
   * {@inheritDoc}
   */
  public function getPrivateFilePath(): ?string {
    return NULL;
  }

  /**
   * Reads an extensions resource into extensions objects.
   *
   * @param $extensions_resource
   *   A serialized extensions resource from which to parse extensions.
   *
   * @return \Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension[]
   *   An array of extensions.
   *
   * @throws \JsonException
   */
  protected static function parseExtensionsFromResource($extensions_resource) : array {
    return array_map(function (array $extension) {
      $extension['status'] = $extension['enabled'];
      return Drupal7Extension::createFromStdClass((object) $extension);
    }, static::parseJsonResource($extensions_resource));
  }

}
