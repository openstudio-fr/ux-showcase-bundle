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

#[AsTwigComponent('Demo:Layout:Card', template: 'showcase/card.html.twig')]
final class Card
{
    public string $title = '';

    /** @var list<array{label: string, meta: array{price: float}}> */
    public array $items = [];
}
