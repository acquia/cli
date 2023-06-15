<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App\From\SourceSite;

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
  protected string $type;

  /**
   * The name of the extension.
   */
  protected string $name;

  /**
   * Whether the extension is enabled or not.
   */
  protected bool $enabled;

  /**
   * The human-readable name of the extension.
   */
  protected string $humanName;

  /**
   * The extension's version.
   */
  protected string $version;

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
    $this->humanName = !empty($human_name) ? $human_name : $name;
    $this->version = $version;
  }

  /**
   * Creates an extension object given a Drush extension object.
   *
   * @param object $extension
   *   An extension object as returned by drush_get_extensions().
   * @return \Acquia\Cli\Command\App\From\SourceSite\Drupal7Extension
   *   A new extension.
   */
  public static function createFromStdClass(object $extension): Drupal7Extension {
    return new static(
      $extension->type,
      $extension->name,
      $extension->status,
      $extension->humanName ?? $extension->name,
      $extension->version ?? 'Unknown',
    );
  }

  public function getName(): string {
    return $this->name;
  }

  public function getHumanName(): string {
    return $this->humanName;
  }

  public function getVersion(): string {
    return $this->version;
  }

  public function isModule(): bool {
    return $this->type === 'module';
  }

  public function isTheme(): bool {
    return $this->type === 'theme';
  }

  public function isEnabled(): bool {
    return $this->enabled;
  }

}
