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

namespace OpenStudio\UxShowcaseBundle\Access;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class RoleShowcaseAccess implements ShowcaseAccessInterface
{
    public function __construct(
        private bool $debug,
        private AuthorizationCheckerInterface $authorizationChecker,
        private string $role,
    ) {
    }

    public function isGranted(Request $request): bool
    {
        return $this->debug || $this->authorizationChecker->isGranted($this->role);
    }
}
