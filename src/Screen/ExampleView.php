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

use OpenStudio\UxShowcaseBundle\Example\Example;

/**
 * An example as the component page shows it.
 *
 * @internal
 */
final readonly class ExampleView
{
    /**
     * @param int|null             $height  fixed height of the frame in pixels, null to follow the content
     * @param list<ResolvedAction> $actions
     */
    public function __construct(
        public Example $example,
        public string $code,
        public string $previewUrl,
        public ?int $height,
        public array $actions,
    ) {
    }
}
