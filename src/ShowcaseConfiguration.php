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

use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Screen\Background;

final readonly class ShowcaseConfiguration
{
    /**
     * @param array<string, int|null>   $viewports   name => width in pixels, null for a responsive preview
     * @param array<string, Background> $backgrounds name => background, in display order
     * @param PreviewOptions            $preview     last level of the preview options, below every library
     */
    public function __construct(
        public string $title,
        public ?string $logo,
        public array $viewports,
        public array $backgrounds,
        public PreviewOptions $preview = new PreviewOptions(),
    ) {
    }
}
