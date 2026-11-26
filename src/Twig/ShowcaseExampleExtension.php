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
 * Registered on the private environment that reads example files, never on the application environment.
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
