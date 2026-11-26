<?php

declare(strict_types=1);

/*
 * This file is part of the openstudio/ux-showcase-bundle package.
 *
 * (c) OpenStudio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenStudio\UxShowcaseBundle\DependencyInjection\Compiler;

use OpenStudio\UxShowcaseBundle\Example\PhpExampleProvider;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires what is only known once every bundle has registered: the examples marked with AsShowcaseExample, and the
 * class components TwigComponent found. Runs after TwigComponentPass, which sets the class map it reads.
 *
 * @internal
 */
final class ShowcasePass implements CompilerPassInterface
{
    public const EXAMPLE_TAG = 'ux_showcase.example';

    public function process(ContainerBuilder $container): void
    {
        $references = [];
        $definitions = [];
        foreach ($container->findTaggedServiceIds(self::EXAMPLE_TAG) as $id => $tags) {
            $references[$id] = new Reference($id);
            foreach ($tags as $tag) {
                /** @var array{method: string, component: string, slug: string, title?: string|null, content?: string|null} $tag */
                $definitions[] = [
                    'service' => $id,
                    'method' => $tag['method'],
                    'component' => $tag['component'],
                    'slug' => $tag['slug'],
                    'title' => $tag['title'] ?? null,
                    'content' => $tag['content'] ?? null,
                ];
            }
        }
        $container->findDefinition(PhpExampleProvider::class)
            ->setArgument(0, ServiceLocatorTagPass::register($container, $references))
            ->setArgument(1, $definitions);

        $factory = $container->findDefinition('ux.twig_component.component_factory');
        $classMap = $factory->getArguments()[5] ?? null;
        if (!\is_array($classMap)) {
            throw new \LogicException('ux_showcase could not read the component class map of symfony/ux-twig-component: its container wiring changed.');
        }
        $container->findDefinition(ComponentCatalog::class)->setArgument('$classMap', $classMap);
    }
}
