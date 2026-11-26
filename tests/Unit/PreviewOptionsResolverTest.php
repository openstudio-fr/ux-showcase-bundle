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

namespace OpenStudio\UxShowcaseBundle\Tests\Unit;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use PHPUnit\Framework\TestCase;

final class PreviewOptionsResolverTest extends TestCase
{
    public function testEachLevelOverridesTheNextOneField(): void
    {
        $resolver = new PreviewOptionsResolver(new ShowcaseConfiguration('t', null, [], [], new PreviewOptions('host', 'host-class', true, 100)));
        $library = $this->library(new PreviewOptions(background: 'library', bodyClass: 'library-class', height: 200), new PreviewOptions(background: 'component', height: 300));
        $example = new Example('Demo:Button', 'a', preview: new PreviewOptions(height: 400, wrapper: false));

        $resolved = $resolver->resolve($library, $example);
        self::assertSame('component', $resolved->background);
        self::assertSame('library-class', $resolved->bodyClass);
        self::assertSame(400, $resolved->height);
        self::assertFalse($resolved->wrapper);

        $screen = $resolver->resolve($library, $example, new PreviewOptions(background: 'screen', height: 500, wrapper: true));
        self::assertSame('screen', $screen->background);
        self::assertSame(500, $screen->height);
        self::assertTrue($screen->wrapper);
    }

    public function testHostConfigurationAnswersWhatNoOtherLevelDecides(): void
    {
        $resolver = new PreviewOptionsResolver(new ShowcaseConfiguration('t', null, [], [], new PreviewOptions('host', 'host-class', false, 100)));

        $resolved = $resolver->resolve($this->library(new PreviewOptions(), new PreviewOptions()), new Example('Demo:Button', 'a'));

        self::assertSame('host', $resolved->background);
        self::assertSame('host-class', $resolved->bodyClass);
        self::assertSame(100, $resolved->height);
        self::assertFalse($resolved->wrapper);
    }

    public function testTheWrapperIsOnWhenNobodyDecides(): void
    {
        $resolver = new PreviewOptionsResolver(new ShowcaseConfiguration('t', null, [], []));

        self::assertTrue($resolver->resolve($this->library(new PreviewOptions(), new PreviewOptions()), new Example('Demo:Button', 'a'))->wrapper);
    }

    private function library(PreviewOptions $library, PreviewOptions $component): ComponentLibraryInterface
    {
        return new class($library, $component) implements ComponentLibraryInterface {
            public function __construct(private readonly PreviewOptions $library, private readonly PreviewOptions $component)
            {
            }

            public function getName(): string
            {
                return 'demo';
            }

            public function getLabel(): string
            {
                return 'Demo';
            }

            public function getComponents(): array
            {
                return [new ComponentReference('Demo:Button', preview: $this->component)];
            }

            public function getGroups(): array
            {
                return [];
            }

            public function getExampleProviders(): iterable
            {
                return [];
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
                return $this->library;
            }
        };
    }
}
