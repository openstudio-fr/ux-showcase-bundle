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
 * The form that lets a visitor try other values of an example (variable `try` of the template `_try.html.twig`).
 *
 * @api
 */
final readonly class TryPanel
{
    /**
     * @param string                $url       signed address the form posts to
     * @param string                $linkUrl   where a page left open asks for a new signed address and token
     * @param int|null              $expires   timestamp at which `$url` stops working
     * @param string                $frameName `name` of the frame of the example: the target of the form without JavaScript
     * @param list<TryField>        $fields
     * @param list<TryReadOnlyProp> $readOnly
     */
    public function __construct(
        public string $url,
        public string $linkUrl,
        public string $token,
        public ?int $expires,
        public string $frameName,
        public array $fields,
        public array $readOnly,
    ) {
    }

    /**
     * @return list<string> the modules of the fields on the page, once each
     */
    public function modules(): array
    {
        return array_values(array_unique(array_filter(array_map(static fn (TryField $field): ?string => $field->module, $this->fields))));
    }
}
