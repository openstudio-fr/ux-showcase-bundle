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

use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;

/**
 * Screen choice, then example, component, library, host configuration: the first level that decides a field wins.
 *
 * @internal
 */
final readonly class PreviewOptionsResolver
{
    public function __construct(private ShowcaseConfiguration $configuration)
    {
    }

    public function resolve(ComponentLibraryInterface $library, Example $example, PreviewOptions $screen = new PreviewOptions()): ResolvedPreview
    {
        $levels = [$screen, $example->preview];
        foreach ($library->getComponents() as $reference) {
            if ($reference->name === $example->component) {
                $levels[] = $reference->preview;
                break;
            }
        }
        $levels[] = $library->getPreviewOptions();
        $levels[] = $this->configuration->preview;

        $background = $bodyClass = $wrapper = $height = $needsData = null;
        foreach ($levels as $level) {
            $background ??= $level->background;
            $bodyClass ??= $level->bodyClass;
            $wrapper ??= $level->wrapper;
            $height ??= $level->height;
            $needsData ??= $level->needsData;
        }

        return new ResolvedPreview($background, $bodyClass, $wrapper ?? true, $height, $needsData ?? false);
    }
}
