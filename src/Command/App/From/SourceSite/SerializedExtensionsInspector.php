<?php

declare(strict_types=1);

namespace AcquiaMigrate\SourceSite;

use AcquiaMigrate\JsonResourceParserTrait;

/**
 * Class SerializedExtensionsInspector.
 *
 * @internal
 */
class SerializedExtensionsInspector extends SiteInspectorBase {

  use JsonResourceParserTrait;

  /**
   * A resource containing exported extension information.
   *
   * @var \AcquiaMigrate\SourceSite\Drupal7Extension[]
   */
  protected $extensions;

  /**
   * SerializedExtensionsInspector constructor.
   *
   * @param \AcquiaMigrate\SourceSite\Drupal7Extension[] $extensions
   *   An array of extensions.
   */
  protected function __construct(array $extensions) {
    $this->extensions = $extensions;
  }

  /**
   * Creates a new ExportInspector.
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
   * @return \AcquiaMigrate\SourceSite\Drupal7Extension[]
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
