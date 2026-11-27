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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\CatalogLibrary;

use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\TwigExampleFileProvider;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;

// A library written in PHP that reuses the component catalog and the Twig example files, and decides groups and labels.
final readonly class CatalogLibrary implements ComponentLibraryInterface
{
    public function __construct(
        private ComponentCatalog $catalog,
        private string $examplesDirectory = __DIR__.'/examples',
    ) {
    }

    public function getName(): string
    {
        return 'native';
    }

    public function getLabel(): string
    {
        return 'Native';
    }

    public function getComponents(): array
    {
        $references = [];
        foreach ($this->catalog->names() as $name) {
            if (!str_starts_with($name, 'Native:')) {
                continue;
            }

            $segments = explode(':', substr($name, \strlen('Native:')));
            $label = 'Base' === end($segments) && \count($segments) > 1 ? $segments[0] : null;
            $group = str_contains($name, ':Form:') ? 'Forms' : 'General';

            $references[] = new ComponentReference($name, $group, label: $label);
        }

        return $references;
    }

    public function getGroups(): array
    {
        return ['General', 'Forms'];
    }

    public function getExampleProviders(): iterable
    {
        yield new TwigExampleFileProvider($this->examplesDirectory);
    }

    public function getWrapperTemplate(): ?string
    {
        return null;
    }

    public function getBareTemplate(): ?string
    {
        return null;
    }

    public function getPreviewOptions(): PreviewOptions
    {
        return new PreviewOptions();
    }
}
