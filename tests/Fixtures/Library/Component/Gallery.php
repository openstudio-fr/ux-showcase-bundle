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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Align;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Layout;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleDto;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Props:Gallery', template: 'showcase/gallery.html.twig')]
final class Gallery
{
    /** @var list<VehicleDto> */
    public array $vehicles = [];

    public Layout $layout = Layout::Grid;

    public ?Align $align = null;

    public int|string $limit = 12;

    /** @var array<string, VehicleDto>|null */
    public ?array $named;

    public static string $notAProp = '';
}
