<?php

declare(strict_types = 1);

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioCiCdVariables;
use Acquia\Cli\Tests\TestBase;

class CodeStudioCiCdVariablesTest extends TestBase {

  public function testGetDefaultsForNode(): void {
    $codeStudioCiCdVariablesObj = new CodeStudioCiCdVariables();
    $variables = $codeStudioCiCdVariablesObj->getDefaultsForNode();
    foreach ($variables as $variable) {
      $maskedValue = $variable['masked'];
      $this->assertEquals(TRUE, $maskedValue);
      $protectedValue = $variable['protected'];
      $this->assertEquals(FALSE, $protectedValue);
    }
  }

  public function testGetDefaultsForPhp(): void {
    $codeStudioCiCdVariablesObj = new CodeStudioCiCdVariables();
    $variables = $codeStudioCiCdVariablesObj->getDefaultsForPhp();
    foreach ($variables as $variable) {
      $maskedValue = $variable['masked'];
      $this->assertEquals(TRUE, $maskedValue);
      $protectedValue = $variable['protected'];
      $this->assertEquals(FALSE, $protectedValue);
    }
  }

}
