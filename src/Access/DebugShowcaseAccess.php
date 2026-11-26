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

final readonly class DebugShowcaseAccess implements ShowcaseAccessInterface
{
    public function __construct(private bool $debug)
    {
    }

    public function isGranted(Request $request): bool
    {
        return $this->debug;
    }
}
