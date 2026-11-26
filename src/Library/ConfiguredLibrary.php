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

use OpenStudio\UxShowcaseBundle\Example\ExampleProviderInterface;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;

/**
 * A library declared under ux_showcase.libraries: every component whose name starts with the prefix.
 *
 * @internal
 */
final class ConfiguredLibrary implements ComponentLibraryInterface
{
    /** @var list<ComponentReference>|null */
    private ?array $components = null;

    /**
     * @param list<string>                   $groups           display order of the groups
     * @param array<string, ComponentStatus> $statuses         component name => status; the others are ready
     * @param list<ExampleProviderInterface> $exampleProviders
     */
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly ?string $prefix,
        private readonly array $groups,
        private readonly array $statuses,
        private readonly ?string $wrapperTemplate,
        private readonly ?string $bareTemplate,
        private readonly PreviewOptions $preview,
        private readonly array $exampleProviders,
        private readonly ComponentCatalog $catalog,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getComponents(): array
    {
        if (null !== $this->components) {
            return $this->components;
        }

        $references = [];
        foreach ($this->catalog->names() as $name) {
            if (null !== $this->prefix && !str_starts_with($name, $this->prefix.':')) {
                continue;
            }

            $relative = null === $this->prefix ? $name : substr($name, \strlen($this->prefix) + 1);
            $position = strrpos($relative, ':');
            $references[] = new ComponentReference(
                $name,
                false === $position ? ComponentReference::DEFAULT_GROUP : substr($relative, 0, $position),
                $this->statuses[$name] ?? ComponentStatus::Ready,
            );
        }

        $order = array_flip($this->groups);
        usort($references, static fn (ComponentReference $left, ComponentReference $right): int => [$order[$left->group] ?? \PHP_INT_MAX, $left->group, $left->name] <=> [$order[$right->group] ?? \PHP_INT_MAX, $right->group, $right->name]);

        return $this->components = $references;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getExampleProviders(): iterable
    {
        return $this->exampleProviders;
    }

    public function getWrapperTemplate(): ?string
    {
        return $this->wrapperTemplate;
    }

    public function getBareTemplate(): ?string
    {
        return $this->bareTemplate;
    }

    public function getPreviewOptions(): PreviewOptions
    {
        return $this->preview;
    }
}
