<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\DocsCommand;
use Acquia\Cli\Tests\CommandTestBase;
use Symfony\Component\Console\Command\Command;

/**
 * Class DocsCommandTest.
 *
 * @property \Acquia\Cli\Command\DocsCommandTest $command
 * @package Acquia\Cli\Tests\Commands
 */
class DocsCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(DocsCommand::class);
  }

  /**
   * Tests the 'docs' command for ACLI.
   *
   * @throws \Exception
   */
  public function testAcliDocCommand(): void {
    $this->docCommandValidate([0], '  [0 ] Acquia CLI');
  }

  /**
   * Tests the 'docs' command for ACMS.
   *
   * @throws \Exception
   */
  public function testACMSDocCommand(): void {
    $this->docCommandValidate([1], '  [1 ] Acquia CMS');
  }

  /**
   * Tests the 'docs' command for Code Studio.
   *
   * @throws \Exception
   */
  public function testCodeStudioDocCommand(): void {
    $this->docCommandValidate([2], '  [2 ] Code Studio');
  }

  /**
   * Tests the 'docs' command for Campaign Studio.
   *
   * @throws \Exception
   */
  public function testCampaignStudioDocCommand(): void {
    $this->docCommandValidate([3], '  [3 ] Campaign Studio');
  }

  /**
   * Tests the 'docs' command for Content Hub.
   *
   * @throws \Exception
   */
  public function testContentHubDocCommand(): void {
    $this->docCommandValidate([4], '  [4 ] Content Hub');
  }

  /**
   * Tests the 'docs' command for Acquia Migrate Accelerate.
   *
   * @throws \Exception
   */
  public function testAMADocCommand(): void {
    $this->docCommandValidate([5], '  [5 ] Acquia Migrate Accelerate');
  }

  /**
   * Tests the 'docs' command for Site Factory.
   *
   * @throws \Exception
   */
  public function testSiteFactoryDocCommand(): void {
    $this->docCommandValidate([6], '  [6 ] Site Factory');
  }

  /**
   * Tests the 'docs' command for Site Studio.
   *
   * @throws \Exception
   */
  public function testSiteStudioDocCommand(): void {
    $this->docCommandValidate([7], '  [7 ] Site Studio');
  }

  /**
   * Tests the 'docs' command for Edge.
   *
   * @throws \Exception
   */
  public function testEdgeDocCommand(): void {
    $this->docCommandValidate([8], '  [8 ] Edge');
  }

  /**
   * Tests the 'docs' command for Search.
   *
   * @throws \Exception
   */
  public function testSearchDocCommand(): void {
    $this->docCommandValidate([9], '  [9 ] Search');
  }

  /**
   * Tests the 'docs' command for Shield.
   *
   * @throws \Exception
   */
  public function testShieldDocCommand(): void {
    $this->docCommandValidate([10], '  [10] Shield');
  }

  /**
   * Tests the 'docs' command for CDP.
   *
   * @throws \Exception
   */
  public function testCDPDocCommand(): void {
    $this->docCommandValidate([11], '  [11] Customer Data Plateform');
  }

  /**
   * Tests the 'docs' command for Cloud IDE.
   *
   * @throws \Exception
   */
  public function testCloudIdeDocCommand(): void {
    $this->docCommandValidate([12], '  [12] Cloud IDE');
  }

  /**
   * Tests the 'docs' command for BLT.
   *
   * @throws \Exception
   */
  public function testBltDocCommand(): void {
    $this->docCommandValidate([13], '  [13] BLT');
  }

  /**
   * Tests the 'docs' command for Cloud Platform.
   *
   * @throws \Exception
   */
  public function testCloudPlatformDocCommand(): void {
    $this->docCommandValidate([14], '  [14] Cloud Platform');
  }

  /**
   * Tests the 'docs' command for Acquia DAM Classic.
   *
   * @throws \Exception
   */
  public function testDamClassicDocCommand(): void {
    $this->docCommandValidate([15], '  [15] Acquia DAM Classic');
  }

  /**
   * Tests the 'docs' command for Personalization.
   *
   * @throws \Exception
   */
  public function testPersonalizationDocCommand(): void {
    $this->docCommandValidate([16], '  [16] Personalization');
  }

  /**
   * Tests the 'docs' command for Campaign Factory.
   *
   * @throws \Exception
   */
  public function testCampaignFactoryDocCommand(): void {
    $this->docCommandValidate([17], '  [17] Campaign Factory');
  }

  /**
   * Helper to run the command.
   *
   * @param array $input
   *   Command input.
   * @param string $message
   *   Message for assertion test.
   *
   * @throws \Exception
   */
  protected function docCommandValidate(array $input, $message): void {
    $this->executeCommand([], $input);
    $output = $this->getDisplay();
    $this->assertStringContainsString('Please select the Acquia Product [Acquia CLI]:', $output);
    $this->assertStringContainsString($message, $output);
  }

}
