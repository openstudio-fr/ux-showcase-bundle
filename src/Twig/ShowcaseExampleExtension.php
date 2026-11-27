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

namespace OpenStudio\UxShowcaseBundle\Twig;

use Twig\Extension\AbstractExtension;

/**
 * Registers the showcase_example / showcase_free tags. The private environment that reads example files and the
 * application one both use it: the application parses an example file (`lint:twig`) and renders nothing for the tags.
 *
 * @internal
 */
final class ShowcaseExampleExtension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [new ExampleTokenParser(false), new ExampleTokenParser(true)];
    }
}
