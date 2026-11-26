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
 * One level of preview options: a null field means "not decided here", the next level answers.
 *
 * @api
 */
final readonly class PreviewOptions
{
    /**
     * @param string|null $background a key of ux_showcase.backgrounds
     * @param string|null $bodyClass  classes of the body of the bare preview page
     * @param bool|null   $wrapper    whether the library wrapper surrounds the example
     * @param int|null    $height     preview height in pixels, null to fit the content
     */
    public function __construct(
        public ?string $background = null,
        public ?string $bodyClass = null,
        public ?bool $wrapper = null,
        public ?int $height = null,
    ) {
        if (null !== $height && $height < 1) {
            throw new \InvalidArgumentException('The preview height is a positive number of pixels.');
        }
    }
}
