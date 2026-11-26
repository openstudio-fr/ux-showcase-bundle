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

namespace OpenStudio\UxShowcaseBundle;

final readonly class ShowcaseConfiguration
{
    /**
     * @param array<string, int|null> $viewports   name => width in pixels, null for a responsive preview
     * @param array<string, string>   $backgrounds name => CSS colour
     */
    public function __construct(
        public string $title,
        public ?string $logo,
        public array $viewports,
        public array $backgrounds,
    ) {
    }
}
