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

namespace OpenStudio\UxShowcaseBundle\Library;

use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;

/**
 * @api
 */
final readonly class ComponentReference
{
    public const DEFAULT_GROUP = 'General';

    public string $label;

    /**
     * @param string               $name   TwigComponent name, `:` separated (`Form:Input`); used as is in URLs, where `:` is legal
     * @param string               $group  group of the library it is listed under
     * @param ComponentStatus|null $status null = the library claims no status: no badge is shown, the component is listed like a ready one
     * @param string|null          $label  defaults to the last segment of the name
     */
    public function __construct(
        public string $name,
        public string $group = self::DEFAULT_GROUP,
        public ?ComponentStatus $status = ComponentStatus::Ready,
        ?string $label = null,
        public PreviewOptions $preview = new PreviewOptions(),
    ) {
        if ('' === $name || '' === $group) {
            throw new \InvalidArgumentException('A component reference needs a name and a group.');
        }

        $this->label = $label ?? (str_contains($name, ':') ? substr($name, (int) strrpos($name, ':') + 1) : $name);
    }
}
