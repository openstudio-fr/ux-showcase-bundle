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

namespace OpenStudio\UxShowcaseBundle\Example\Attribute;

/**
 * Marks a class (its `__invoke()`) or a method that returns the props of an example. The class is autoconfigured:
 * declare it as a service and it is found.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class AsShowcaseExample
{
    public function __construct(
        public string $component,
        public string $slug,
        public ?string $title = null,
        public ?string $content = null,
    ) {
    }
}
