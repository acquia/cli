<?php

declare(strict_types=1);

namespace Acquia\Cli\Tests\Commands\CodeStudio;

use Acquia\Cli\Command\CodeStudio\CodeStudioCiCdVariables;
use Acquia\Cli\Tests\TestBase;

class CodeStudioCiCdVariablesTest extends TestBase
{
    public function testGetDefaultsForNode(): void
    {
        $codeStudioCiCdVariablesObj = new CodeStudioCiCdVariables();
        $variables = $codeStudioCiCdVariablesObj->getDefaultsForNode();
        $this->testBooleanValues($variables);
        $variables = $codeStudioCiCdVariablesObj->getDefaultsForPhp();
        $this->testBooleanValues($variables);
    }

    protected function testBooleanValues(array $variables): void
    {
        foreach ($variables as $variable) {
            if ($variable['key'] !== "MYSQL_VERSION" && $variable['key'] !== "PHP_VERSION" && $variable['key'] !== "NODE_VERSION" && $variable['key'] !== "NODE_HOSTING_TYPE") {
                $maskedValue = $variable['masked'];
                $this->assertEquals(true, $maskedValue);
            } else {
                $maskedValue = $variable['masked'];
                $this->assertEquals(false, $maskedValue);
            }
            $protectedValue = $variable['protected'];
            $this->assertEquals(false, $protectedValue);
        }
    }
}
