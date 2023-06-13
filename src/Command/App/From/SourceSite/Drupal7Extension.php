<?php

declare(strict_types=1);

namespace AcquiaMigrate\SourceSite;

use AcquiaMigrate\ExtensionInterface;

/**
 * Represents a Drupal 7 extension.
 */
final class Drupal7Extension implements ExtensionInterface {

  /**
   * The type of the extension.
   *
   * @var string
   *   Either 'module' or 'theme'.
   */
  protected $type;

  /**
   * The name of the extension.
   *
   * @var string
   */
  protected $name;

  /**
   * Whether the extension is enabled or not.
   *
   * @var bool
   */
  protected $enabled;

  /**
   * The human-readable name of the extension.
   *
   * @var string
   */
  protected $humanName;

  /**
   * The extension's version.
   *
   * @var string
   */
  protected $version;

  /**
   * Extension constructor.
   *
   * @param string $type
   *   The extension type. Either 'module' or 'theme'.
   * @param string $name
   *   The extension name.
   * @param bool $enabled
   *   Whether the extension is enabled or not.
   * @param string $human_name
   *   The human-readable name of the extension.
   * @param string $version
   *   The extension version.
   */
  protected function __construct(string $type, string $name, bool $enabled, string $human_name, string $version) {
    assert(in_array($type, ['module', 'theme']));
    $this->type = $type;
    $this->name = $name;
    $this->enabled = $enabled;
    $this->humanName = $human_name ?? $name;
    $this->version = $version;
  }

  /**
   * Creates an extension object given a Drush extension object.
   *
   * @param object $extension
   *   An extension object as returned by drush_get_extensions().
   *
   * @return \AcquiaMigrate\SourceSite\Drupal7Extension
   *   A new extension.
   */
  public static function createFromStdClass(object $extension) : Drupal7Extension {
    return new static(
      $extension->type,
      $extension->name,
      $extension->status,
      $extension->humanName ?? $extension->name,
      $extension->version ?? 'Unknown',
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritDoc}
   */
  public function getHumanName(): string {
    return $this->humanName;
  }

  /**
   * {@inheritDoc}
   */
  public function getVersion(): string {
    return $this->version;
  }

  /**
   * {@inheritDoc}
   */
  public function isModule() : bool {
    return $this->type === 'module';
  }

  /**
   * {@inheritDoc}
   */
  public function isTheme() : bool {
    return $this->type === 'theme';
  }

  /**
   * {@inheritDoc}
   */
  public function isEnabled() : bool {
    return $this->enabled;
  }

}
