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
 * The preview options are the ones of a Twig example (`background`, `body_class`, `wrapper`, `height`, `needs_data`);
 * left null, an option is not decided here and the next level (component, library, global) answers.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class AsShowcaseExample
{
    /**
     * @param string|null $background a key of ux_showcase.backgrounds
     * @param string|null $bodyClass  classes of the body of the bare preview page
     * @param bool|null   $wrapper    whether the library wrapper surrounds the example
     * @param int|null    $height     preview height in pixels
     * @param bool|null   $needsData  whether the example reads data the check command does not have
     */
    public function __construct(
        public string $component,
        public string $slug,
        public ?string $title = null,
        public ?string $content = null,
        public ?string $background = null,
        public ?string $bodyClass = null,
        public ?bool $wrapper = null,
        public ?int $height = null,
        public ?bool $needsData = null,
    ) {
    }
}
