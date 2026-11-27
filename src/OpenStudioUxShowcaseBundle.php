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

namespace OpenStudio\UxShowcaseBundle;

use OpenStudio\UxShowcaseBundle\Access\DebugShowcaseAccess;
use OpenStudio\UxShowcaseBundle\Access\RoleShowcaseAccess;
use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use OpenStudio\UxShowcaseBundle\DependencyInjection\Compiler\RequireSecurityForRolePass;
use OpenStudio\UxShowcaseBundle\DependencyInjection\Compiler\ShowcasePass;
use OpenStudio\UxShowcaseBundle\Example\Attribute\AsShowcaseExample;
use OpenStudio\UxShowcaseBundle\Example\ExampleProviderInterface;
use OpenStudio\UxShowcaseBundle\Example\FixtureLoader;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\TwigExampleFileProvider;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;
use OpenStudio\UxShowcaseBundle\Library\ConfiguredLibrary;
use OpenStudio\UxShowcaseBundle\Preview\CheckRequestFactoryInterface;
use OpenStudio\UxShowcaseBundle\Preview\PostedValuesNormalizerInterface;
use OpenStudio\UxShowcaseBundle\Props\PropsReaderInterface;
use OpenStudio\UxShowcaseBundle\Screen\Background;
use OpenStudio\UxShowcaseBundle\Screen\ComponentActionInterface;
use OpenStudio\UxShowcaseBundle\Screen\TryFieldTypeInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class OpenStudioUxShowcaseBundle extends AbstractBundle
{
    protected string $extensionAlias = 'ux_showcase';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RequireSecurityForRolePass());
        // After TwigComponentPass (priority 0), which sets the class map of the components.
        $container->addCompilerPass(new ShowcasePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    /**
     * @param array{
     *     title: string,
     *     logo: string|null,
     *     viewports: array<string, int|null>,
     *     backgrounds: array<string, array{color: string, html_attributes: array<string, string|int|float|bool>}>,
     *     fixtures_directory: string|null,
     *     preview: array{background: string|null, body_class: string|null, wrapper: bool, height: int|null, needs_data: bool},
     *     libraries: array<string, array{label: string|null, prefix: string|null, examples: string|null, groups: list<string>, wrapper: string|null, bare: string|null, preview: array{background: string|null, body_class: string|null, wrapper: bool|null, height: int|null, needs_data: bool|null}, needs_data: list<string>, statuses: array<string, ComponentStatus>}>,
     *     access: array{role: string|null, service: string|null},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $services = $container->services();
        $backgrounds = [];
        foreach ($config['backgrounds'] as $name => $background) {
            $backgrounds[$name] = inline_service(Background::class)->args([$name, $background['color'], $background['html_attributes']]);
        }
        $services->set(ShowcaseConfiguration::class)
            ->args([$config['title'], $config['logo'], $config['viewports'], $backgrounds, inline_service(PreviewOptions::class)->args(array_values($config['preview']))]);
        $services->set(FixtureLoader::class)->args([$config['fixtures_directory']]);

        $builder->registerForAutoconfiguration(ComponentLibraryInterface::class)->addTag('ux_showcase.library');
        $builder->registerForAutoconfiguration(CheckRequestFactoryInterface::class)->addTag('ux_showcase.check_request_factory');
        $builder->registerForAutoconfiguration(PropsReaderInterface::class)->addTag('ux_showcase.props_reader');
        $builder->registerForAutoconfiguration(PostedValuesNormalizerInterface::class)->addTag('ux_showcase.posted_values_normalizer');
        $builder->registerForAutoconfiguration(ComponentActionInterface::class)->addTag('ux_showcase.action');
        $builder->registerForAutoconfiguration(TryFieldTypeInterface::class)->addTag('ux_showcase.try_field_type');
        $builder->registerForAutoconfiguration(ExampleProviderInterface::class)->addTag('ux_showcase.example_provider');
        $builder->registerAttributeForAutoconfiguration(
            AsShowcaseExample::class,
            static function (ChildDefinition $definition, AsShowcaseExample $attribute, \Reflector $reflector): void {
                $definition->addTag(ShowcasePass::EXAMPLE_TAG, [
                    'method' => $reflector instanceof \ReflectionMethod ? $reflector->getName() : '__invoke',
                    'component' => $attribute->component,
                    'slug' => $attribute->slug,
                    'title' => $attribute->title,
                    'content' => $attribute->content,
                    'background' => $attribute->background,
                    'body_class' => $attribute->bodyClass,
                    'wrapper' => $attribute->wrapper,
                    'height' => $attribute->height,
                    'needs_data' => $attribute->needsData,
                ]);
            },
        );

        foreach ($config['libraries'] as $name => $library) {
            $providers = [];
            if (null !== $library['examples']) {
                $services->set('ux_showcase.examples.'.$name, TwigExampleFileProvider::class)->args([$library['examples'], $library['prefix']]);
                $providers[] = service('ux_showcase.examples.'.$name);
            }

            $services->set('ux_showcase.library.'.$name, ConfiguredLibrary::class)
                ->args([
                    $name,
                    $library['label'] ?? ucfirst($name),
                    $library['prefix'],
                    $library['groups'],
                    $library['statuses'],
                    $library['wrapper'],
                    $library['bare'],
                    inline_service(PreviewOptions::class)->args(array_values($library['preview'])),
                    $providers,
                    service(ComponentCatalog::class),
                    $library['needs_data'],
                ])
                ->tag('ux_showcase.library');
        }

        $access = $config['access'];

        if (null !== $access['service']) {
            $services->alias(ShowcaseAccessInterface::class, $access['service']);

            return;
        }

        if (null !== $access['role']) {
            $services->set(RoleShowcaseAccess::class)
                ->args([param('kernel.debug'), service('security.authorization_checker'), $access['role']]);
            $services->alias(ShowcaseAccessInterface::class, RoleShowcaseAccess::class);

            return;
        }

        $services->set(DebugShowcaseAccess::class)->args([param('kernel.debug')]);
        $services->alias(ShowcaseAccessInterface::class, DebugShowcaseAccess::class);
    }
}
