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

use OpenStudio\UxShowcaseBundle\Access\RoleShowcaseAccess;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RequireSecurityForRolePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(RoleShowcaseAccess::class) && !$container->has('security.authorization_checker')) {
            throw new \LogicException('"ux_showcase.access.role" is set but the Symfony security component is not available: install symfony/security-bundle and enable SecurityBundle, or use "ux_showcase.access.service".');
        }
    }
}
