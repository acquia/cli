<?php

declare(strict_types=1);

namespace Acquia\Cli;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Manages an environment made of bundles.
 */
class Kernel extends BaseKernel
{
    /**
     * @return array<mixed>
     */
    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->getProjectDir() . '/config/' . $this->getEnvironment() . '/services.yml');
    }

    /** @infection-ignore-all */
    public function getCacheDir(): string
    {
        $testToken = getenv('TEST_TOKEN') ?? '';
        return parent::getCacheDir() . $testToken;
    }

    protected function build(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addCompilerPass($this->createCollectingCompilerPass());
    }

    /**
     * Creates a collecting compiler pass.
     */
    private function createCollectingCompilerPass(): CompilerPassInterface
    {
        return new class implements CompilerPassInterface {
            public function process(ContainerBuilder $containerBuilder): void
            {
                $appDefinition = $containerBuilder->findDefinition(Application::class);
                $dispatcherDefinition = $containerBuilder->findDefinition(EventDispatcher::class);

                foreach ($containerBuilder->getDefinitions() as $definition) {
                    // Handle event listeners.
                    if ($definition->hasTag('kernel.event_listener')) {
                        foreach ($definition->getTag('kernel.event_listener') as $tag) {
                            $dispatcherDefinition->addMethodCall('addListener', [
                                $tag['event'],
                                [
                                    new ServiceClosureArgument(new Reference($definition->getClass())),
                                    $tag['method'],
                                ],
                            ]);
                        }
                    }

                    // Handle commands.
                    if (!is_a($definition->getClass(), Command::class, true)) {
                        continue;
                    }

                    // Without this, Symfony tries to instantiate our abstract base command. No bueno.
                    if ($definition->isAbstract()) {
                        continue;
                    }

                    $appDefinition->addMethodCall('add', [
                        new Reference($definition->getClass()),
                    ]);
                }

                $appDefinition->addMethodCall('setDispatcher', [
                    $dispatcherDefinition,
                ]);
            }
        };
    }
}
