<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\App\From\SourceSite;

abstract class SiteInspectorBase implements SiteInspectorInterface {

  /**
   * {@inheritDoc}
   */
  public function getExtensions(int $flags): array {
    $state_flags = $flags & (SiteInspectorInterface::FLAG_EXTENSION_ENABLED | SiteInspectorInterface::FLAG_EXTENSION_DISABLED);
    $type_flags = $flags & (SiteInspectorInterface::FLAG_EXTENSION_MODULE | SiteInspectorInterface::FLAG_EXTENSION_THEME);
    return array_filter($this->readExtensions(), function (ExtensionInterface $extension) use ($state_flags, $type_flags) {
      // Generate a flag for the extension's enabled/disabled state.
      $has = $extension->isEnabled() ? SiteInspectorInterface::FLAG_EXTENSION_ENABLED : SiteInspectorInterface::FLAG_EXTENSION_DISABLED;
      // Incorporate the extension's type.
      $has = $has | ($extension->isModule() ? SiteInspectorInterface::FLAG_EXTENSION_MODULE : 0);
      $has = $has | ($extension->isTheme() ? SiteInspectorInterface::FLAG_EXTENSION_THEME : 0);
      // TRUE if the extension has a flag in $type_flags AND a flag in
      // $state_flags, FALSE otherwise.
      return ($has & $type_flags) && ($has & $state_flags);
    });
  }

  /**
   * Returns a list of extensions discovered on the inspected site.
   *
   * @return \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface[]
   *   An array of extensions discovered on the inspected source site.
   */
  abstract protected function readExtensions(): array;

}
