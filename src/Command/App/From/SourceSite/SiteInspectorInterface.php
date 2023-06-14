<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App\From\SourceSite;

/**
 * Interface for a class which can inspect a Drupal 7 site.
 */
interface SiteInspectorInterface {
  /**
   * Flag adding disabled extensions.
   *
   * @see \Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface::getExtensions()
   */
  public const FLAG_EXTENSION_DISABLED = 1 << 1;
  /**
   * Flag adding themes.
   *
   * @see \Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface::getExtensions()
   */
  public const FLAG_EXTENSION_THEME = 1 << 3;
  /**
   * Flag adding modules.
   *
   * @see \Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface::getExtensions()
   */
  public const FLAG_EXTENSION_MODULE = 1 << 2;
  /**
   * Flag adding enabled extensions.
   *
   * @see \Acquia\Cli\Command\App\From\SourceSite\SiteInspectorInterface::getExtensions()
   */
  public const FLAG_EXTENSION_ENABLED = 1 << 0;

  /**
   * Gets extensions on an inspected site.
   *
   * @param int $flags
   *   Bitwise flags indicting various subsets of extensions to be returned.
   *   Omitting flags omits those extensions from the return value. I.e. if the
   *   FLAG_EXTENSION_ENABLED flag is given, but not the FLAG_EXTENSION_DISABLED
   *   flag, then only enabled extensions will be returned. In the example
   *   below, all enabled modules will be returned. Themes and disabled modules
   *   will be excluded.
   * @code
   *   $inspector->getExtensions(Drupal7SiteInspector::FLAG_EXTENSION_ENABLED|Drupal7SiteInspector::FLAG_EXTENSION_MODULE);
   * @endcode
   * @return \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface[]
   *   An array of identified extensions, filtered to contain only those
   *   included by the given flags.
   */
  public function getExtensions(int $flags): array;

  /**
   * Gets the public file path relative to the Drupal root.
   */
  public function getPublicFilePath(): string;

  /**
   * Gets the private file path relative to the Drupal root, if it exists.
   *
   * @return string|null
   *   NULL if the the inspected site does not use private files, a string
   *   otherwise.
   */
  public function getPrivateFilePath(): ?string;

}
