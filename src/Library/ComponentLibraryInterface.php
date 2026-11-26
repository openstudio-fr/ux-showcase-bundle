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
 * Autoconfigured (tag `ux_showcase.library`).
 *
 * @api
 */
interface ComponentLibraryInterface
{
    /**
     * Unique, matches [a-z0-9]+(-[a-z0-9]+)*: it is the library segment of the URLs.
     */
    public function getName(): string;

    public function getLabel(): string;

    /**
     * Every component of the library, hidden ones included, in display order.
     *
     * @return list<ComponentReference>
     */
    public function getComponents(): array;

    /**
     * Group names in display order. A group of a component or of a documentation page that is missing here is listed
     * after these, alphabetically.
     *
     * @return list<string>
     */
    public function getGroups(): array;

    /**
     * @return iterable<ExampleProviderInterface>
     */
    public function getExampleProviders(): iterable;

    /**
     * Twig template that surrounds an example (a root controller, a live component the example needs). It receives
     * `content` (the rendered example, safe markup), `example`, `library` and `preview`.
     */
    public function getWrapperTemplate(): ?string;

    /**
     * Twig template of the bare page a preview is served in. Rendered by the preview screen, with the same variables as
     * the wrapper.
     */
    public function getBareTemplate(): ?string;

    public function getPreviewOptions(): PreviewOptions;
}
