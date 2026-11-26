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
 * @internal
 */
final readonly class ResolvedAction
{
    public function __construct(
        public string $name,
        public string $label,
        public ActionTarget $target,
    ) {
    }
}
