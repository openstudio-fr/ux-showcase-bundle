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

namespace OpenStudio\UxShowcaseBundle\Example;

/**
 * @api
 */
final readonly class ResolvedPreview
{
    public function __construct(
        public ?string $background,
        public ?string $bodyClass,
        public bool $wrapper,
        public ?int $height,
        public bool $needsData = false,
    ) {
    }
}
