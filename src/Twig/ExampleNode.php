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

namespace OpenStudio\UxShowcaseBundle\Twig;

use Twig\Node\Node;

/**
 * Carries the data of a showcase_example / showcase_free tag. It is read from the tree and never compiled.
 *
 * @internal
 */
final class ExampleNode extends Node
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $options
     */
    public function __construct(bool $free, string $slug, ?string $title, array $props, array $options, int $line)
    {
        parent::__construct([], ['free' => $free, 'slug' => $slug, 'title' => $title, 'props' => $props, 'options' => $options], $line);
    }
}
