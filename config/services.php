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
use OpenStudio\UxShowcaseBundle\Command\CheckCommand;
use OpenStudio\UxShowcaseBundle\Controller\AssetController;
use OpenStudio\UxShowcaseBundle\Controller\ComponentController;
use OpenStudio\UxShowcaseBundle\Controller\DocPageController;
use OpenStudio\UxShowcaseBundle\Controller\IndexController;
use OpenStudio\UxShowcaseBundle\Controller\PreviewController;
use OpenStudio\UxShowcaseBundle\Controller\TriedPreviewController;
use OpenStudio\UxShowcaseBundle\EventListener\BarePageListener;
use OpenStudio\UxShowcaseBundle\EventListener\ShowcaseAccessListener;
use OpenStudio\UxShowcaseBundle\EventListener\SignedPreviewListener;
use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Example\ExampleRenderer;
use OpenStudio\UxShowcaseBundle\Example\PhpExampleProvider;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use OpenStudio\UxShowcaseBundle\Preview\BarePage;
use OpenStudio\UxShowcaseBundle\Preview\PostedValuesResolver;
use OpenStudio\UxShowcaseBundle\Preview\PreviewPrefill;
use OpenStudio\UxShowcaseBundle\Preview\PreviewRequestGuard;
use OpenStudio\UxShowcaseBundle\Preview\PreviewUrlGeneratorInterface;
use OpenStudio\UxShowcaseBundle\Preview\SignedPreviewUrlGenerator;
use OpenStudio\UxShowcaseBundle\Preview\TypedValueConverter;
use OpenStudio\UxShowcaseBundle\Preview\ValueSerializer;
use OpenStudio\UxShowcaseBundle\Props\ClassPropsReader;
use OpenStudio\UxShowcaseBundle\Props\ComponentDocumentation;
use OpenStudio\UxShowcaseBundle\Props\TemplatePropsReader;
use OpenStudio\UxShowcaseBundle\Props\TemplateSourceReader;
use OpenStudio\UxShowcaseBundle\Screen\ActionResolver;
use OpenStudio\UxShowcaseBundle\Screen\Assets;
use OpenStudio\UxShowcaseBundle\Screen\BuiltinTryFieldType;
use OpenStudio\UxShowcaseBundle\Screen\ExampleViewFactory;
use OpenStudio\UxShowcaseBundle\Screen\TryFieldRegistry;
use OpenStudio\UxShowcaseBundle\Screen\TryPanelFactory;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use OpenStudio\UxShowcaseBundle\Twig\ShowcaseExampleExtension;
use OpenStudio\UxShowcaseBundle\Twig\ShowcaseScreenExtension;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    foreach ([IndexController::class, ComponentController::class, DocPageController::class, PreviewController::class, AssetController::class] as $controller) {
        $services->set($controller)
            ->autowire()
            ->tag('controller.service_arguments');
    }

    $services->set(CheckCommand::class)
        ->args([service(ShowcaseRegistry::class), service(PreviewOptionsResolver::class), service('http_kernel'), service('router'), service('request_stack'), service('twig'), tagged_iterator('ux_showcase.check_request_factory')])
        ->tag('console.command');

    $services->set(ShowcaseAccessListener::class)
        ->args([service(ShowcaseAccessInterface::class)])
        ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onController'])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

    $services->set(Assets::class)->args([service('router')]);
    $services->set(ShowcaseScreenExtension::class)->args([service(Assets::class)])->tag('twig.extension');
    // The tags of the example files, known to the application so that `lint:twig` parses them; they render nothing.
    $services->set(ShowcaseExampleExtension::class)->tag('twig.extension');
    // Before the profiler of the framework (kernel.response, -100): it must be off when it decides to collect.
    $services->set(BarePageListener::class)
        ->args([service(Assets::class), service('profiler')->nullOnInvalid()])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onRequest', 'priority' => 8])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

    $services->set(SignedPreviewListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);

    $services->set(ActionResolver::class)->args([tagged_iterator('ux_showcase.action')]);
    $services->set(ExampleViewFactory::class)
        ->args([service(ExampleCodeGenerator::class), service(PreviewOptionsResolver::class), service(ActionResolver::class), service('router'), service(TryPanelFactory::class)]);

    $services->set(ComponentCatalog::class)
        ->args([service('ux.twig_component.component_factory'), service('twig.loader.native_filesystem'), []]);

    // Arguments set by ShowcasePass, once every example is known.
    $services->set(PhpExampleProvider::class)
        ->args([[], []])
        ->tag('ux_showcase.example_provider');

    // Outside debug the registry keeps its build here; `cache:clear` empties it.
    $services->set('ux_showcase.cache', FilesystemAdapter::class)
        ->args(['ux_showcase', 0, '%kernel.cache_dir%/ux_showcase']);

    $services->set(ShowcaseRegistry::class)
        ->args([tagged_iterator('ux_showcase.library'), tagged_iterator('ux_showcase.example_provider'), service('ux_showcase.cache'), param('kernel.debug')]);

    // The template first: it holds the descriptions; the class then refines the types.
    $services->set(TemplateSourceReader::class)->args([service('twig')]);
    $services->set(TemplatePropsReader::class)
        ->args([service(ComponentCatalog::class), service('twig'), service(TemplateSourceReader::class)])
        ->tag('ux_showcase.props_reader', ['priority' => 20]);
    $services->set(ClassPropsReader::class)
        ->args([service(ComponentCatalog::class)])
        ->tag('ux_showcase.props_reader', ['priority' => 10]);
    $services->set(ComponentDocumentation::class)
        ->args([service(ComponentCatalog::class), tagged_iterator('ux_showcase.props_reader')]);

    $services->set(ExampleCodeGenerator::class);
    $services->set(PreviewOptionsResolver::class)->args([service(ShowcaseConfiguration::class)]);
    $services->set(ExampleRenderer::class)
        ->args([service('twig'), service(ExampleCodeGenerator::class), service(PreviewOptionsResolver::class)]);

    // The preview of the values a visitor tried. A host replaces the address by aliasing PreviewUrlGeneratorInterface.
    $services->set(SignedPreviewUrlGenerator::class)->args([service('router'), param('kernel.secret')]);
    $services->alias(PreviewUrlGeneratorInterface::class, SignedPreviewUrlGenerator::class);
    $services->set(PreviewRequestGuard::class)->args([service('security.csrf.token_manager')->nullOnInvalid()]);
    $services->set(ValueSerializer::class);
    $services->set(TypedValueConverter::class)->args([service(ValueSerializer::class)]);
    $services->set(PostedValuesResolver::class)->args([service(TypedValueConverter::class), tagged_iterator('ux_showcase.posted_values_normalizer')]);
    $services->set(PreviewPrefill::class)->args([service(ComponentDocumentation::class), service(TypedValueConverter::class), service(ValueSerializer::class)]);
    // The fields of the Try form: the built-in ones come last (priority -100), a host field type that supports a prop wins.
    foreach ([['text', ['text']], ['number', ['integer', 'number']], ['checkbox', ['boolean']], ['select', ['choice']], ['json', ['json']]] as [$name, $kinds]) {
        $services->set('ux_showcase.try_field.'.$name, BuiltinTryFieldType::class)
            ->args([$name, '@OpenStudioUxShowcase/try/field/'.$name.'.html.twig', $kinds, service(TypedValueConverter::class)])
            ->tag('ux_showcase.try_field_type', ['priority' => -100]);
    }
    $services->set(TryFieldRegistry::class)->args([tagged_iterator('ux_showcase.try_field_type')]);
    $services->set(TryPanelFactory::class)
        ->args([service(PreviewPrefill::class), service(TryFieldRegistry::class), service(TypedValueConverter::class), service(PreviewUrlGeneratorInterface::class), service(PreviewRequestGuard::class), service('router')]);
    $services->set(BarePage::class)
        ->args([service('twig'), service(ShowcaseConfiguration::class), service(ShowcaseRegistry::class), service(ExampleRenderer::class), service(PreviewOptionsResolver::class)]);
    $services->set(TriedPreviewController::class)
        ->args([
            service(ShowcaseRegistry::class),
            service(ComponentDocumentation::class),
            service(SignedPreviewUrlGenerator::class),
            service(PreviewUrlGeneratorInterface::class),
            service(PreviewRequestGuard::class),
            service(PostedValuesResolver::class),
            service(BarePage::class),
            service('twig'),
            service('translator')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');
};
