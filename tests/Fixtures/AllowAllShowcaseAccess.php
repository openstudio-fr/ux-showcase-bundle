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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures;

use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class AllowAllShowcaseAccess implements ShowcaseAccessInterface
{
    public function isGranted(Request $request): bool
    {
        return true;
    }
}
