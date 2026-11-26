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

use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Screen\Assets;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @internal
 */
final class ShowcaseScreenExtension extends AbstractExtension
{
    public function __construct(private readonly Assets $assets)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('ux_showcase_asset', $this->assets->url(...))];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('ux_showcase_value', $this->value(...))];
    }

    /**
     * A default or an allowed value as a Twig literal, which is how it is written in a template.
     */
    public function value(mixed $value): string
    {
        try {
            return ExampleCodeGenerator::literal($value);
        } catch (\InvalidArgumentException) {
            return get_debug_type($value);
        }
    }
}
