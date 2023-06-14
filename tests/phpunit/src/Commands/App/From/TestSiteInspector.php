<?php declare(strict_types=1);

namespace AcquiaMigrate\Tests;

use AcquiaMigrate\SourceSite\SiteInspectorBase;

/**
 * Mock site inspector.
 *
 * This makes it easy to test the rest of the codebase without mocking drush's
 * global functions.
 */
final class TestSiteInspector extends SiteInspectorBase {

  /**
   * @var \AcquiaMigrate\ExtensionInterface[]
   */
  protected $extensions;

  /**
   * @var string
   */
  protected $filePublicPath;

  /**
   * @var string|null
   */
  protected $filePrivatePath;

  /**
   * TestSiteInspector constructor.
   *
   * @param \AcquiaMigrate\ExtensionInterface[] $extensions
   *   An array of extensions.
   */
  public function __construct(array $extensions = [], $file_public_path = 'sites/default/files', $file_private_path = NULL) {
    $this->extensions = $extensions;
    $this->filePublicPath = $file_public_path;
    $this->filePrivatePath = $file_private_path;
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
    return $this->filePublicPath;
  }

  /**
   * {@inheritDoc}
   */
  public function getPrivateFilePath(): ?string {
    return $this->filePrivatePath;
  }

}
