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

namespace OpenStudio\UxShowcaseBundle\Screen;

/**
 * A preview background: a CSS colour, and attributes for the `<html>` element of the bare page (`data-bs-theme="dark"`).
 *
 * @api
 */
final readonly class Background
{
    /**
     * @param array<string, string|int|float|bool> $htmlAttributes
     */
    public function __construct(
        public string $name,
        public string $color,
        public array $htmlAttributes = [],
    ) {
    }

    /**
     * The attributes of the `<html>` element: the configured ones, the colour as an inline style, the name of the background.
     *
     * @return array<string, string>
     */
    public function toHtmlAttributes(): array
    {
        $attributes = [];
        foreach ($this->htmlAttributes as $name => $value) {
            $attributes[$name] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        $style = 'background-color: '.$this->color;
        $attributes['style'] = isset($attributes['style']) && '' !== $attributes['style'] ? rtrim($attributes['style'], '; ').'; '.$style : $style;
        $attributes['data-ux-showcase-background'] = $this->name;

        return $attributes;
    }
}
