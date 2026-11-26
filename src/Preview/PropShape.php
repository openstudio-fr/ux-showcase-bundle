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

namespace OpenStudio\UxShowcaseBundle\Preview;

/**
 * What a form needs to know about the declared type of an editable prop: the kind of control, whether it takes null,
 * the values to choose from, whether a text field may carry a number (`int|string`).
 *
 * @internal
 */
final readonly class PropShape
{
    public const TEXT = 'text';
    public const INTEGER = 'integer';
    public const NUMBER = 'number';
    public const BOOLEAN = 'boolean';
    public const CHOICE = 'choice';
    public const JSON = 'json';

    /**
     * @param self::*                          $kind
     * @param list<bool|int|float|string|null> $choices the values of a choice, null excluded (see $nullable)
     * @param bool                             $numeric a text that reads as a number is sent as a number (`int|string`)
     */
    public function __construct(
        public string $kind,
        public bool $nullable = false,
        public array $choices = [],
        public bool $numeric = false,
    ) {
    }
}
