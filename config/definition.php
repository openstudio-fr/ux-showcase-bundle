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

use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    $root = $definition->rootNode();
    assert($root instanceof ArrayNodeDefinition);
    $root
        ->children()
            ->scalarNode('title')->defaultValue('Components')->cannotBeEmpty()->end()
            ->scalarNode('logo')->defaultNull()->end()
            ->arrayNode('viewports')
                ->info('Preview widths, in display order: name => width in pixels, or null for a responsive preview.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->requiresAtLeastOneElement()
                ->variablePrototype()
                    ->validate()
                        ->ifTrue(static fn (mixed $width): bool => null !== $width && (!is_int($width) || $width < 1))
                        ->thenInvalid('A viewport width is a positive number of pixels, or null for a responsive preview.')
                    ->end()
                ->end()
                ->defaultValue(['responsive' => null, 'mobile' => 375, 'tablet' => 768, 'desktop' => 1280])
            ->end()
            ->arrayNode('backgrounds')
                ->info('Preview backgrounds, in display order: name => CSS colour.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->requiresAtLeastOneElement()
                ->scalarPrototype()->cannotBeEmpty()->end()
                ->defaultValue(['light' => '#f8f9fa', 'white' => '#ffffff', 'dark' => '#212529'])
            ->end()
            ->scalarNode('fixtures_directory')
                ->info('Directory the JSON fixtures of PHP examples are loaded from, by relative path.')
                ->defaultNull()
            ->end()
            ->arrayNode('preview')
                ->info('Last level of the preview options, below every library.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('background')->info('A key of backgrounds.')->defaultNull()->end()
                    ->scalarNode('body_class')->defaultNull()->end()
                    ->booleanNode('wrapper')->info('Whether the wrapper of a library surrounds the examples.')->defaultTrue()->end()
                    ->integerNode('height')->min(1)->defaultNull()->end()
                ->end()
            ->end()
            ->arrayNode('libraries')
                ->info('Libraries declared from configuration; a library that needs PHP implements ComponentLibraryInterface instead.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('label')->info('Defaults to the name of the library.')->defaultNull()->end()
                        ->scalarNode('prefix')->info('The TwigComponent namespace it covers: "Flexy" covers Flexy:Button and Flexy:Form:Input. Every component when null.')->defaultNull()->end()
                        ->scalarNode('examples')->info('Directory of its Twig example files (*.showcase.twig).')->defaultNull()->end()
                        ->arrayNode('groups')
                            ->info('Display order of the groups.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('wrapper')->info('Twig template that surrounds its examples.')->defaultNull()->end()
                        ->scalarNode('bare')->info('Twig template of its bare preview page.')->defaultNull()->end()
                        ->arrayNode('preview')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('background')->defaultNull()->end()
                                ->scalarNode('body_class')->defaultNull()->end()
                                ->booleanNode('wrapper')->defaultNull()->end()
                                ->integerNode('height')->min(1)->defaultNull()->end()
                            ->end()
                        ->end()
                        ->arrayNode('statuses')
                            ->info('Component name => ready, waiting or hidden; the other components are ready.')
                            ->useAttributeAsKey('component')
                            ->normalizeKeys(false)
                            ->enumPrototype()->enumFqcn(ComponentStatus::class)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('access')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('role')
                        ->info('A Symfony role that opens the showcase outside debug (needs symfony/security-bundle).')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('service')
                        ->info('Id of a ShowcaseAccessInterface service that replaces the default rule entirely.')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end()
        ->end()
    ;
};
