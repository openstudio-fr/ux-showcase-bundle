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
use OpenStudio\UxShowcaseBundle\Example\ExampleProviderInterface;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RegistryCacheTest extends TestCase
{
    public function testTheBuildIsKeptOutsideDebugAndSharedByTheNextRegistry(): void
    {
        $library = new CountingLibrary();
        $cache = new ArrayAdapter();

        $first = new ShowcaseRegistry([$library], [], $cache, false);
        self::assertSame(['Demo:Button'], array_map(static fn (ComponentReference $reference): string => $reference->name, $first->components('demo')));
        self::assertSame(['one'], array_map(static fn (Example $example): string => $example->slug, $first->examples('demo', 'Demo:Button')));
        self::assertSame(1, $library->builds);

        // Another request: a new registry on the same pool does not read the components nor the examples again.
        $second = new ShowcaseRegistry([$library], [], $cache, false);
        self::assertSame(['one'], array_map(static fn (Example $example): string => $example->slug, $second->examples('demo', 'Demo:Button')));
        self::assertSame('Demo:Button', $second->component('demo', 'Demo:Button')?->name);
        self::assertSame(1, $library->builds);
        self::assertSame('Demo', $second->library('demo')?->getLabel());
    }

    public function testDebugAlwaysRebuildsSoAnEditedExampleShowsAtOnce(): void
    {
        $library = new CountingLibrary();
        $cache = new ArrayAdapter();

        (new ShowcaseRegistry([$library], [], $cache, true))->examples('demo', 'Demo:Button');
        (new ShowcaseRegistry([$library], [], $cache, true))->examples('demo', 'Demo:Button');

        self::assertSame(2, $library->builds);
    }

    public function testWithoutAPoolNothingIsKept(): void
    {
        $library = new CountingLibrary();

        (new ShowcaseRegistry([$library], []))->examples('demo', 'Demo:Button');
        (new ShowcaseRegistry([$library], []))->examples('demo', 'Demo:Button');

        self::assertSame(2, $library->builds);
    }
}

final class CountingLibrary implements ComponentLibraryInterface
{
    public int $builds = 0;

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
        ++$this->builds;

        return [new ComponentReference('Demo:Button')];
    }

    public function getGroups(): array
    {
        return [];
    }

    public function getExampleProviders(): iterable
    {
        yield new class implements ExampleProviderInterface {
            public function getExamples(): iterable
            {
                yield new Example('Demo:Button', 'one', props: ['label' => 'One']);
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
