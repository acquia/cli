<?php

namespace Acquia\Cli;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Manages an environment made of bundles.
 */
class Kernel extends BaseKernel {

  public function registerBundles(): iterable {
    return [];
  }

  public function registerContainerConfiguration(LoaderInterface $loader): void {
    $loader->load($this->getProjectDir() . '/config/' . $this->getEnvironment() . '/services.yml');
    $this->registerExtensionConfiguration($loader);
  }

  protected function registerExtensionConfiguration($loader): void {
    // Search for plugins.
    $finder = new Finder();
    $extensions = $finder->files()
      ->in([
        __DIR__ . '/../../',
      ])
      ->depth(1)
      ->name('acli.services.yml');
    foreach ($extensions as $extension) {
      $loader->load($extension->getRealPath());
    }
  }

  /**
   * Returns a loader for the container.
   *
   * @return \Symfony\Component\Config\Loader\DelegatingLoader The loader
   */
  protected function getContainerLoader(ContainerInterface $container): DelegatingLoader {
    $locator = new FileLocator([$this->getProjectDir()]);
    $resolver = new LoaderResolver([
      new YamlFileLoader($container, $locator),
      new DirectoryLoader($container, $locator),
    ]);

    return new DelegatingLoader($resolver);
  }

  protected function build(ContainerBuilder $container_builder): void {
    $container_builder->addCompilerPass($this->createCollectingCompilerPass());
  }

  /**
   * Creates a collecting compiler pass.
   */
  private function createCollectingCompilerPass(): CompilerPassInterface {
    return new class implements CompilerPassInterface {

      public function process(ContainerBuilder $container_builder) {
        $app_definition = $container_builder->findDefinition(Application::class);
        $dispatcher_definition = $container_builder->findDefinition(EventDispatcher::class);

        foreach ($container_builder->getDefinitions() as $definition) {
          // Handle event listeners.
          if ($definition->hasTag('kernel.event_listener')) {
            foreach ($definition->getTag('kernel.event_listener') as $tag) {
              $dispatcher_definition->addMethodCall('addListener', [
                $tag['event'],
                [
                  new ServiceClosureArgument(new Reference($definition->getClass())),
                  $tag['method'],
                ],
              ]);
            }
          }

          // Handle commands.
          if (!is_a($definition->getClass(), Command::class, TRUE)) {
            continue;
          }

          // Without this, Symfony tries to instantiate our abstract base command. No bueno.
          if ($definition->isAbstract()) {
            continue;
          }

          $app_definition->addMethodCall('add', [
            new Reference($definition->getClass()),
          ]);
        }

        $app_definition->addMethodCall('setDispatcher', [
          $dispatcher_definition,
        ]);
      }

    };
  }

}
