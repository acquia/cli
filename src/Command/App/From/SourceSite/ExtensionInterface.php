<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\App\From\SourceSite;

/**
 * Interface for a Drupal 7 extension.
 */
interface ExtensionInterface {

  /**
   * Gets the extension's name.
   *
   * @return string
   *   The extension's name.
   */
  public function getName(): string;

  /**
   * Gets the extension's human-readable name.
   *
   * @return string
   *   The extension's human-readable name.
   */
  public function getHumanName(): string;

  /**
   * Gets the extension's version.
   *
   * @return string
   *   The extension's version.
   */
  public function getVersion(): string;

  /**
   * Whether the extension is a module or not.
   *
   * @return bool
   *   TRUE if the extension is a module; FALSE otherwise.
   */
  public function isModule() : bool;

  /**
   * Whether the extension is a theme or not.
   *
   * @return bool
   *   TRUE if the extension is a theme; FALSE otherwise.
   */
  public function isTheme() : bool;

  /**
   * Whether the extension is enabled or not.
   *
   * @return bool
   *   TRUE if the extension is enabled; FALSE otherwise.
   */
  public function isEnabled() : bool;

}
