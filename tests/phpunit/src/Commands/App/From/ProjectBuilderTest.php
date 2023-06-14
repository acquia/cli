<?php declare(strict_types=1);

namespace AcquiaMigrate\Tests;

use AcquiaMigrate\Composer\ProjectBuilder;
use AcquiaMigrate\Configuration;
use AcquiaMigrate\Recommendation\Recommendations;
use AcquiaMigrate\Recommendation\Resolver;
use PHPUnit\Framework\TestCase;

final class ProjectBuilderTest extends TestCase {

  /**
   * @dataProvider getTestResources
   * @param $configuration_resource
   * @param $recommendations_resource
   * @throws \JsonException
   */
  public function test($configuration_resource, $recommendations_resource, array $expected_project_definition) {
    assert(is_resource($configuration_resource));
    assert(is_resource($recommendations_resource));
    $configuration = Configuration::createFromResource($configuration_resource);
    $site_inspector = new TestSiteInspector();
    $resolver = new Resolver($site_inspector, Recommendations::createFromResource($recommendations_resource));
    $project_builder = new ProjectBuilder($configuration, $resolver, $site_inspector);
    $this->assertSame($project_builder->buildProject(), $expected_project_definition);
  }

  public function getTestResources() {
    $test_cases = [
      'simplest case, sanity check' => [
        json_encode([
          'sourceModules' => [],
          'filePaths' => [
            'public' => 'sites/default/files',
            'private' => NULL,
          ],
          'rootPackageDefinition' => [],
        ]),
        json_encode([
          'data' => []
        ]),
        [
          'installModules' => [],
          'filePaths' => [
            'public' => 'sites/default/files',
            'private' => NULL,
          ],
          'sourceModules' => [],
          'recommendations' => [],
          'rootPackageDefinition' => [],
        ],
      ],
    ];
    return array_map(function (array $data) {
      [$configuration_json, $recommendation_json, $expectation] = $data;
      $config_resource = fopen('php://memory', 'rw');
      fwrite($config_resource, $configuration_json);
      rewind($config_resource);
      $recommendation_resource = fopen('php://memory', 'rw');
      fwrite($recommendation_resource, $recommendation_json);
      rewind($recommendation_resource);
      return [$config_resource, $recommendation_resource, $expectation];
    }, $test_cases);
  }

}
