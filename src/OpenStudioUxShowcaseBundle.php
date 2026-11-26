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
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class OpenStudioUxShowcaseBundle extends AbstractBundle
{
    protected string $extensionAlias = 'ux_showcase';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RequireSecurityForRolePass());
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
     *     backgrounds: array<string, string>,
     *     access: array{role: string|null, service: string|null},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $services = $container->services();
        $services->set(ShowcaseConfiguration::class)
            ->args([$config['title'], $config['logo'], $config['viewports'], $config['backgrounds']]);

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
