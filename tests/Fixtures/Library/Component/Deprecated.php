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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Raises a deprecation when it is mounted, silenced the way Symfony code does it.
 */
#[AsTwigComponent('Check:Deprecated', template: 'components/Check/Deprecated.html.twig')]
final class Deprecated
{
    public function mount(): void
    {
        @trigger_error('The component "Check:Deprecated" is deprecated.', \E_USER_DEPRECATED);
    }
}
