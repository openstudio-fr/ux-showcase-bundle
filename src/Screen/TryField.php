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

use OpenStudio\UxShowcaseBundle\Props\PropDoc;

/**
 * A field of the Try form, as the template of its type gets it (variable `field`).
 *
 * @api
 */
final readonly class TryField
{
    /**
     * @param string                                                                                      $type    name of the TryFieldTypeInterface that draws it
     * @param string                                                                                      $kind    `text`, `integer`, `number`, `boolean`, `choice`, `json`, or `` when the declared type says none
     * @param mixed                                                                                       $value   JSON-safe value of the example, or of the default of the prop; only meaningful when $hasValue
     * @param string                                                                                      $json    $value as pretty-printed JSON, empty without value
     * @param string                                                                                      $initial $value as compact JSON, empty without value
     * @param list<array{value: bool|int|float|string|null, label: string, json: string, selected: bool}> $choices
     * @param bool                                                                                        $sent    the example sets the prop: it is always sent; the others only once the visitor changed them
     */
    public function __construct(
        public PropDoc $prop,
        public string $name,
        public string $id,
        public string $errorId,
        public string $hintId,
        public string $describedBy,
        public string $type,
        public string $template,
        public ?string $module,
        public string $kind,
        public bool $nullable,
        public bool $numeric,
        public array $choices,
        public bool $hasValue,
        public mixed $value,
        public string $json,
        public string $initial,
        public bool $sent,
    ) {
    }
}
