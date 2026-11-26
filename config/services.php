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

use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use OpenStudio\UxShowcaseBundle\Controller\IndexController;
use OpenStudio\UxShowcaseBundle\EventListener\ShowcaseAccessListener;
use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Example\ExampleRenderer;
use OpenStudio\UxShowcaseBundle\Example\PhpExampleProvider;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(IndexController::class)
        ->autowire()
        ->tag('controller.service_arguments');

    $services->set(ShowcaseAccessListener::class)
        ->args([service(ShowcaseAccessInterface::class)])
        ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onController'])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

    $services->set(ComponentCatalog::class)
        ->args([service('ux.twig_component.component_factory'), service('twig.loader.native_filesystem'), []]);

    // Arguments set by ShowcasePass, once every example is known.
    $services->set(PhpExampleProvider::class)
        ->args([[], []])
        ->tag('ux_showcase.example_provider');

    $services->set(ShowcaseRegistry::class)
        ->args([tagged_iterator('ux_showcase.library'), tagged_iterator('ux_showcase.example_provider')]);

    $services->set(ExampleCodeGenerator::class);
    $services->set(PreviewOptionsResolver::class)->args([service(ShowcaseConfiguration::class)]);
    $services->set(ExampleRenderer::class)
        ->args([service('twig'), service(ExampleCodeGenerator::class), service(PreviewOptionsResolver::class)]);
};
