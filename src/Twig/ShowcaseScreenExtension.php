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
        return [
            new TwigFilter('ux_showcase_value', $this->value(...)),
            new TwigFilter('ux_showcase_breakable', $this->breakable(...), ['is_safe' => ['html']]),
        ];
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

    /**
     * A label escaped, with a line break allowed (`<wbr>`) before each capital that follows a lowercase letter or a
     * digit and after `_`, `-`, `.`, `/`: a long name (`AccountCompanyBuyers`) wraps between its words.
     */
    public function breakable(string $label): string
    {
        $escaped = htmlspecialchars($label, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace(['/(?<=[\p{Ll}\d])(?=\p{Lu})/u', '/(?<=[_\-.\/])(?=[^_\-.\/])/u'], '<wbr>', $escaped);
    }
}
