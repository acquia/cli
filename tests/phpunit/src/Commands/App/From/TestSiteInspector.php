<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\App\From;

use Acquia\Cli\Command\App\From\SourceSite\SiteInspectorBase;

/**
 * Mock site inspector.
 *
 * This makes it easy to test the rest of the codebase without mocking drush's
 * global functions.
 */
final class TestSiteInspector extends SiteInspectorBase {

  /**
   * TestSiteInspector constructor.
   *
   * @param \Acquia\Cli\Command\App\From\SourceSite\ExtensionInterface[] $extensions
   *   An array of extensions.
   */
  public function __construct(
    protected array $extensions = [],
    protected string $filePublicPath = 'sites/default/files',
    protected ?string $filePrivatePath = NULL
  ) {}

  /**
   * {@inheritDoc}
   */
  protected function readExtensions(): array {
    return $this->extensions;
  }

  public function getPublicFilePath(): string {
    return $this->filePublicPath;
  }

  public function getPrivateFilePath(): ?string {
    return $this->filePrivatePath;
  }

}
