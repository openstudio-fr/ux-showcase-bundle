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
