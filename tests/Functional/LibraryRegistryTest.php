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

namespace OpenStudio\UxShowcaseBundle\Tests\Functional;

use OpenStudio\UxShowcaseBundle\Exception\DuplicateExampleException;
use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;

final class LibraryRegistryTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testLibrariesAreListedWhetherConfiguredOrWrittenInPhp(): void
    {
        $registry = $this->registry();

        $names = array_map(static fn ($library): string => $library->getName(), $registry->libraries());
        self::assertSame(['demo', 'other'], $names);
        self::assertSame('Demo kit', $registry->library('demo')?->getLabel());
        self::assertNull($registry->library('missing'));
    }

    public function testGroupsFollowTheConfiguredOrderAndHiddenComponentsAreAbsent(): void
    {
        $groups = $this->registry()->groups('demo');

        self::assertSame(['General', 'Form', 'Layout', 'Foundations', 'Experimental'], array_map(static fn ($group): string => $group->name, $groups));
        $byGroup = [];
        foreach ($groups as $group) {
            $byGroup[$group->name] = array_map(static fn ($reference): string => $reference->name, $group->components);
        }
        self::assertSame(['Demo:Button'], $byGroup['General']);
        self::assertSame(['Demo:Form:Counter', 'Demo:Form:Input'], $byGroup['Form']);
        self::assertSame(['Demo:Layout:Card', 'Demo:Layout:CompanyBuyersAccountOverviewSummary'], $byGroup['Layout']);
        self::assertSame([], $byGroup['Foundations']);
        self::assertSame(['Demo:Experimental:Chart'], $byGroup['Experimental']);

        $names = array_map(static fn ($reference): string => $reference->name, $this->registry()->components('demo'));
        self::assertNotContains('Demo:Legacy:Old', $names);
        self::assertNull($this->registry()->component('demo', 'Demo:Legacy:Old'));
    }

    public function testAConfiguredLibraryOrdersItsComponentsByGroupThenName(): void
    {
        $library = $this->registry()->library('demo');
        self::assertNotNull($library);

        self::assertSame(
            ['Demo:Button', 'Demo:Form:Counter', 'Demo:Form:Input', 'Demo:Layout:Card', 'Demo:Layout:CompanyBuyersAccountOverviewSummary', 'Demo:Experimental:Chart', 'Demo:Legacy:Old'],
            array_map(static fn ($reference): string => $reference->name, $library->getComponents()),
        );
    }

    public function testStatusesAndLabels(): void
    {
        $registry = $this->registry();

        self::assertSame(ComponentStatus::Waiting, $registry->component('demo', 'Demo:Experimental:Chart')?->status);
        self::assertSame(ComponentStatus::Ready, $registry->component('demo', 'Demo:Button')?->status);
        self::assertSame('Chart', $registry->component('demo', 'Demo:Experimental:Chart')->label);
        self::assertSame(ComponentStatus::Waiting, $registry->component('other', 'Other:Note')?->status);
    }

    public function testComponentWithNoStatusIsVisibleAndDefaultStaysReady(): void
    {
        $registry = $this->registry();

        self::assertNull($registry->component('other', 'Other:Plain')?->status);
        self::assertContains('Other:Plain', array_map(static fn ($reference): string => $reference->name, $registry->components('other')));
        self::assertSame(ComponentStatus::Ready, (new ComponentReference('X:Y'))->status);
        self::assertNull($registry->component('demo', 'Demo:Legacy:Old'));
    }

    public function testExamplesOfEachSourceAreListedInDeclarationOrder(): void
    {
        $registry = $this->registry();

        $button = $registry->examples('demo', 'Demo:Button');
        self::assertSame(['primary', 'danger', 'with-icon', 'in-a-toolbar'], array_map(static fn ($example): string => $example->slug, $button));
        self::assertSame('Primary', $button[0]->title);
        self::assertSame('Dangerous action', $button[1]->title);
        self::assertSame(['label' => 'Delete', 'variant' => 'danger'], $button[1]->props);
        self::assertSame('dark', $button[1]->preview->background);
        self::assertSame(120, $button[1]->preview->height);
        self::assertSame('<span class="icon">&rarr;</span>', $button[2]->content);
        self::assertFalse($button[2]->preview->wrapper);

        $card = $registry->examples('demo', 'Demo:Layout:Card');
        self::assertSame(['blocks', 'article'], array_map(static fn ($example): string => $example->slug, $card));
        $items = $card[1]->props['items'];
        self::assertIsArray($items);
        self::assertSame(['label' => 'Book', 'meta' => ['price' => 12.5]], $items[0]);
        self::assertSame('article-page', $card[1]->preview->bodyClass);
        self::assertSame(320, $card[1]->preview->height);
        self::assertTrue($card[1]->preview->needsData);
        self::assertNull($card[1]->preview->background);
        self::assertNull($card[1]->preview->wrapper);

        self::assertSame(['hello'], array_map(static fn ($example): string => $example->slug, $registry->examples('other', 'Other:Note')));
    }

    public function testDocumentationPagesAreFreeExamplesListedUnderTheirGroup(): void
    {
        $registry = $this->registry();

        $pages = $registry->docPages('demo');
        self::assertCount(2, $pages);
        self::assertTrue($pages[0]->isFree());
        self::assertNull($pages[0]->component);
        self::assertSame('Foundations', $pages[0]->group);
        self::assertSame($pages[0], $registry->findExample('demo', null, 'colours'));
        self::assertSame($pages[0], $registry->groups('demo')[3]->docPages[0]);
        self::assertSame(['colours', 'spacing'], array_map(static fn ($page): string => $page->slug, $registry->groups('demo')[3]->docPages));
    }

    public function testFindExample(): void
    {
        $registry = $this->registry();

        self::assertSame('primary', $registry->findExample('demo', 'Demo:Button', 'primary')?->slug);
        self::assertNull($registry->findExample('demo', 'Demo:Button', 'nope'));
        self::assertNull($registry->findExample('demo', 'Demo:Legacy:Old', 'any'));
    }

    public function testAnUnknownLibraryFailsWithItsName(): void
    {
        $this->expectExceptionMessage('Unknown library "nope": known libraries are demo, other.');
        $this->registry()->components('nope');
    }

    public function testADuplicateSlugFailsWithAClearMessage(): void
    {
        $registry = $this->registry(Configuration::demo(['examples' => __DIR__.'/../Fixtures/LibraryBroken/duplicate']));

        $this->expectException(DuplicateExampleException::class);
        $this->expectExceptionMessage('The example "primary" of the component "Demo:Button" is declared twice in the library "demo"');
        $registry->libraries();
    }

    public function testAnExampleForAnUnknownComponentFailsWithAClearMessage(): void
    {
        $registry = $this->registry(Configuration::demo(['examples' => __DIR__.'/../Fixtures/LibraryBroken/unknown']));

        $this->expectException(UnknownComponentException::class);
        $this->expectExceptionMessage('The example "one" targets the component "Demo:Missing:Thing", which the library "demo" does not list.');
        $registry->libraries();
    }

    /**
     * @param array<string, mixed>|null $configuration
     */
    private function registry(?array $configuration = null): ShowcaseRegistry
    {
        // One kernel per test: a second one would leave the exception handler of the first registered.
        $this->kernel?->shutdown();
        $this->kernel = Configuration::kernel(configuration: $configuration ?? []);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        \assert($container instanceof \Psr\Container\ContainerInterface);
        $registry = $container->get(ShowcaseRegistry::class);
        self::assertInstanceOf(ShowcaseRegistry::class, $registry);

        return $registry;
    }
}
