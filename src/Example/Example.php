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

use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;

/**
 * A component with props and optional markup, or a free Twig snippet.
 *
 * - Component example: `component`, `props` (plain data), `content` (markup between the tags of the component, named
 *   blocks included as `<twig:block name="…">`).
 * - Free example: `source` (a Twig snippet, no props, no form). With a `component`, it is listed among its examples;
 *   without, it is a documentation page and `group` says where it is listed.
 *
 * @api
 */
final readonly class Example
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public string $title;

    /**
     * @param string|null          $component TwigComponent name
     * @param string               $slug      stable, unique within the component (within the library for a documentation page)
     * @param string|null          $title     defaults to the slug, capitalised
     * @param ComponentStatus|null $status    documentation page only: an optional badge (Ready, Waiting) in the index, the menu and the page; null = none
     * @param array<string, mixed> $props     scalars, nulls and arrays of them only: the form is filled from it and the code generated from it
     */
    public function __construct(
        public ?string $component,
        public string $slug,
        ?string $title = null,
        public array $props = [],
        public ?string $content = null,
        public ?string $source = null,
        public ?string $group = null,
        public PreviewOptions $preview = new PreviewOptions(),
        public ?ComponentStatus $status = null,
    ) {
        if (1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw new \InvalidArgumentException(\sprintf('The example slug "%s" is not valid: lowercase letters, digits and single dashes only.', $slug));
        }

        $this->title = $title ?? ucfirst(str_replace('-', ' ', $slug));

        if (null === $component && (null === $source || null === $group)) {
            throw new \InvalidArgumentException(\sprintf('The example "%s" has no component: it is a documentation page, which needs a source and a group.', $slug));
        }

        if (null !== $component && null !== $group) {
            throw new \InvalidArgumentException(\sprintf('The example "%s" has a component: only a documentation page has a group.', $slug));
        }

        if (null !== $status && null !== $component) {
            throw new \InvalidArgumentException(\sprintf('The example "%s" has a component: only a documentation page has a status.', $slug));
        }

        if (ComponentStatus::Hidden === $status) {
            throw new \InvalidArgumentException(\sprintf('The documentation page "%s" cannot be hidden: leave it out instead.', $slug));
        }

        if (null !== $source && ([] !== $props || null !== $content)) {
            throw new \InvalidArgumentException(\sprintf('The example "%s" is free: it takes neither props nor content.', $slug));
        }

        foreach ($props as $name => $value) {
            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', (string) $name)) {
                throw new \InvalidArgumentException(\sprintf('The prop "%s" of the example "%s" is not a valid attribute name.', $name, $slug));
            }
            self::assertPlainData($value, \sprintf('The prop "%s" of the example "%s"', $name, $slug));
        }
    }

    public function isFree(): bool
    {
        return null !== $this->source;
    }

    private static function assertPlainData(mixed $value, string $path): void
    {
        if (null === $value || \is_scalar($value)) {
            return;
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                self::assertPlainData($item, \sprintf('%s, key "%s",', $path, $key));
            }

            return;
        }

        throw new \InvalidArgumentException(\sprintf('%s holds a %s: props are plain data (scalars, nulls, arrays).', $path, get_debug_type($value)));
    }
}
