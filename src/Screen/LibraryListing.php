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

use OpenStudio\UxShowcaseBundle\Library\ComponentGroup;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;

/**
 * The libraries with their groups, as the index and the side menu of every screen list them.
 *
 * @internal
 */
final readonly class LibraryListing
{
    public function __construct(private ShowcaseRegistry $registry)
    {
    }

    /**
     * @return list<array{library: ComponentLibraryInterface, groups: list<ComponentGroup>, without_example: array<string, true>}>
     */
    public function libraries(): array
    {
        $libraries = [];
        foreach ($this->registry->libraries() as $library) {
            $groups = $this->registry->groups($library->getName());
            $withoutExample = [];
            foreach ($groups as $group) {
                foreach ($group->components as $component) {
                    if ([] === $this->registry->examples($library->getName(), $component->name)) {
                        $withoutExample[$component->name] = true;
                    }
                }
            }
            $libraries[] = ['library' => $library, 'groups' => $groups, 'without_example' => $withoutExample];
        }

        return $libraries;
    }
}
