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

namespace OpenStudio\UxShowcaseBundle\Props;

/**
 * A block a component template defines, which a caller can fill.
 *
 * @api
 */
final readonly class BlockDoc
{
    public function __construct(
        public string $name,
        public string $description = '',
    ) {
    }
}
