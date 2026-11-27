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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\ExampleProviderInterface;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;

// A library written in PHP, found by autoconfiguration alone.
final class ServiceLibrary implements ComponentLibraryInterface
{
    public function getName(): string
    {
        return 'other';
    }

    public function getLabel(): string
    {
        return 'Other';
    }

    public function getComponents(): array
    {
        return [
            new ComponentReference('Other:Note', 'Notes', ComponentStatus::Waiting, 'Note'),
            new ComponentReference('Other:Plain', 'Notes', null, 'Plain'),
        ];
    }

    public function getGroups(): array
    {
        return ['Notes'];
    }

    public function getExampleProviders(): iterable
    {
        yield new class implements ExampleProviderInterface {
            public function getExamples(): iterable
            {
                yield new Example('Other:Note', 'hello', props: ['text' => 'Hello']);
                yield new Example('Other:Plain', 'hello', props: ['text' => 'Hello']);
            }
        };
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
