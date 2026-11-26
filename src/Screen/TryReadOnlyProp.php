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
 * A prop the visitor cannot try, shown with the reason.
 *
 * @api
 */
final readonly class TryReadOnlyProp
{
    public const REASON_UNTYPED = 'untyped';
    public const REASON_TYPE = 'type';

    /**
     * @param self::REASON_* $reason
     */
    public function __construct(
        public PropDoc $prop,
        public string $reason,
        public bool $hasValue,
        public mixed $value,
    ) {
    }
}
