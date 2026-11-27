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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;

final class ScreenTest extends TestCase
{
    private ?TestKernel $kernel = null;
    private ?KernelBrowser $client = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        $this->client = null;
    }

    public function testIndexListsLibrariesThenGroupsInTheOrderOfTheLibrary(): void
    {
        $crawler = $this->get('/_showcase/');

        self::assertSame(['Demo kit', 'Other'], $this->texts($crawler, '//section[contains(@class, "showcase-library")]/h2'));
        $demo = $crawler->filterXPath('//section[@aria-labelledby="library-demo"]');
        self::assertSame(['General', 'Form', 'Layout', 'Foundations', 'Experimental'], $this->texts($demo, '//h3'));
        self::assertSame(['Button', 'Counter', 'Input', 'Card', 'CompanyBuyersAccountOverviewSummary', 'Colours', 'Spacing', 'Chart'], $this->texts($demo, '//li/a'));
    }

    public function testIndexShowsStatusBadgesAndHidesHiddenComponents(): void
    {
        $crawler = $this->get('/_showcase/');

        $chart = $crawler->filterXPath('//li[a[normalize-space()="Chart"]]');
        self::assertSame('Waiting', trim($chart->filterXPath('//span[contains(@class, "showcase-badge")]')->text()));
        $button = $crawler->filterXPath('//li[a[normalize-space()="Button"]]');
        self::assertSame('Ready', trim($button->filterXPath('//span[contains(@class, "showcase-badge")]')->text()));
        self::assertStringNotContainsString('Demo:Legacy:Old', (string) $this->client()->getResponse()->getContent());
    }

    public function testTheIndexShowsTheSideMenuAndKeepsItsFullListInTheMain(): void
    {
        $crawler = $this->get('/_showcase/');

        $menu = $crawler->filterXPath('//nav[@id="showcase-menu"]');
        self::assertCount(1, $menu);
        self::assertSame(['Demo kit', 'Other'], $this->texts($menu, '//h2'));
        self::assertSame('/_showcase/demo/Demo:Form:Input', $menu->filterXPath('//a[starts-with(normalize-space(), "Input")]')->attr('href'));
        self::assertSame('/_showcase/demo/_docs/colours', $menu->filterXPath('//a[normalize-space()="Colours"]')->attr('href'));
        self::assertSame(['Waiting'], $this->texts($menu, '//li[a[starts-with(normalize-space(), "Chart")]]//span[contains(@class, "showcase-badge")]'));
        self::assertCount(0, $menu->filterXPath('//li[a[normalize-space()="Plain"]]//span[contains(@class, "showcase-badge")]'));
        self::assertSame(['page'], $this->attributes($menu, '//a[@aria-current]', 'aria-current'));
        self::assertSame('/_showcase/', $menu->filterXPath('//a[@aria-current]')->attr('href'));
        self::assertCount(1, $crawler->filterXPath('//main//section[@aria-labelledby="library-demo"]'));
    }

    public function testTheComponentPageMarksItsEntryInTheSideMenu(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Form:Input');

        $current = $crawler->filterXPath('//nav[@id="showcase-menu"]//a[@aria-current="page"]');
        self::assertCount(1, $current);
        self::assertSame('/_showcase/demo/Demo:Form:Input', $current->attr('href'));
    }

    public function testTheDocumentationPageMarksItsEntryInTheSideMenu(): void
    {
        $crawler = $this->get('/_showcase/demo/_docs/colours');

        $current = $crawler->filterXPath('//nav[@id="showcase-menu"]//a[@aria-current="page"]');
        self::assertCount(1, $current);
        self::assertSame('/_showcase/demo/_docs/colours', $current->attr('href'));
    }

    public function testThePreviewPageHasNoSideMenu(): void
    {
        $this->get('/_showcase/demo/Demo:Button/primary/preview');

        self::assertStringNotContainsString('showcase-menu', (string) $this->client()->getResponse()->getContent());
    }

    public function testThePrimaryColourIsRenderedWhenConfiguredAndAbsentOtherwise(): void
    {
        $this->get('/_showcase/');
        self::assertStringNotContainsString('--showcase-primary', (string) $this->client()->getResponse()->getContent());

        $this->tearDown();
        $this->kernel = Configuration::kernel(configuration: Configuration::demo() + ['theme' => ['primary_color' => '#7b2cbf']]);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $this->client = $client;

        $body = $this->get('/_showcase/demo/Demo:Button')->filterXPath('//body');
        self::assertSame('--showcase-primary: #7b2cbf', $body->attr('style'));
    }

    public function testThePageBackgroundIsRenderedBesideThePrimaryColourWhenConfigured(): void
    {
        $this->tearDown();
        $this->kernel = Configuration::kernel(configuration: Configuration::demo() + ['theme' => ['primary_color' => '#7b2cbf', 'page_background' => '#fff5f1']]);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $this->client = $client;

        foreach (['/_showcase/', '/_showcase/demo/Demo:Button', '/_showcase/demo/_docs/colours'] as $path) {
            self::assertSame('--showcase-primary: #7b2cbf; --showcase-page-background: #fff5f1', $this->get($path)->filterXPath('//body')->attr('style'), $path);
        }
    }

    public function testAnExampleIsNotBoxedAndTheCurrentOneIsNotOutlined(): void
    {
        $this->get('/_showcase/_assets/showcase.css');
        $css = (string) $this->client()->getResponse()->getContent();

        self::assertDoesNotMatchRegularExpression('/\.showcase-example\s*\{[^}]*(border|background)\s*:/', $css);
        self::assertStringNotContainsString('.showcase-example.is-current', $css);
        self::assertMatchesRegularExpression('/\.showcase\s*\{[^}]*background:\s*var\(--showcase-page-background,/', $css);
    }

    public function testPreviewsTheTryFormAndTheListsOfTheHomePageSitInTheBlocksOfTheToolkit(): void
    {
        $this->get('/_showcase/_assets/showcase.css');
        $css = (string) $this->client()->getResponse()->getContent();

        self::assertMatchesRegularExpression('/\.showcase-block, \.showcase-frame, \.showcase-try \{[^}]*background-color: var\(--showcase-block-background, #fff\);[^}]*border: 1px solid[^}]*border-radius: 1rem;/', $css);
        self::assertCount(2, $this->get('/_showcase/')->filterXPath('//main//section[contains(@class, "showcase-library")]/div[@class="showcase-block"]'));
        self::assertGreaterThan(0, $this->get('/_showcase/demo/Demo:Button')->filterXPath('//article//div[@class="showcase-frame"]/iframe')->count());
        self::assertCount(1, $this->get('/_showcase/demo/_docs/colours')->filterXPath('//article//div[@class="showcase-frame"]/iframe'));
    }

    public function testTheBlockBackgroundIsRenderedWhenConfigured(): void
    {
        $this->tearDown();
        $this->kernel = Configuration::kernel(configuration: Configuration::demo() + ['theme' => ['block_background' => '#fffdf8']]);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $this->client = $client;

        self::assertSame('--showcase-block-background: #fffdf8', $this->get('/_showcase/')->filterXPath('//body')->attr('style'));
    }

    public function testWithNoBackgroundNeitherTheHeaderNorTheBottomBarOffersOne(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');
        self::assertCount(1, $crawler->filterXPath('//*[@data-ux-showcase-footer]//select[@data-override="background"]'));
        self::assertGreaterThan(0, $crawler->filterXPath('//a[@data-background]')->count());

        $this->tearDown();
        $this->kernel = Configuration::kernel(configuration: Configuration::demo() + ['backgrounds' => []]);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $this->client = $client;

        $crawler = $this->get('/_showcase/demo/Demo:Button?background=dark');
        self::assertCount(0, $crawler->filterXPath('//a[@data-background] | //*[@id="controls-background"] | //select[@data-override="background"] | //label[@for="footer-background"]'));
        self::assertCount(1, $crawler->filterXPath('//a[@data-viewport][@aria-current]'), 'the width is still offered');
        self::assertCount(1, $crawler->filterXPath('//select[@data-override="viewport"]'));
        self::assertStringNotContainsString('background=dark', (string) $this->client()->getResponse()->getContent());
    }

    public function testTheBottomBarMakesRoomForTheDebugToolbarAndThePreviewStaysBesideTheTryForm(): void
    {
        $crawler = $this->get('/_showcase/');
        $script = $this->asset((string) $crawler->filterXPath('//script[@src]')->attr('src'));
        $style = $this->asset((string) $crawler->filterXPath('//link[@rel="stylesheet"]')->attr('href'));

        self::assertStringContainsString('.sf-toolbarreset', $script);
        self::assertStringContainsString('.sf-toolbar-toggle-button', $script);
        self::assertMatchesRegularExpression('/\.showcase-js \.showcase-footer \{[^}]*bottom: var\(--showcase-bottom-offset, 0px\)/', $style);
        self::assertMatchesRegularExpression('/\.showcase-preview-with-try \.showcase-preview-main \{[^}]*position: sticky;[^}]*top: 1rem;/', $style);
    }

    public function testComponentWithNoStatusIsListedAndShownWithoutBadge(): void
    {
        $crawler = $this->get('/_showcase/');

        $plain = $crawler->filterXPath('//main//li[a[normalize-space()="Plain"]]');
        self::assertCount(1, $plain);
        self::assertCount(0, $plain->filterXPath('//span[contains(@class, "showcase-badge")]'));
        $note = $crawler->filterXPath('//main//li[a[normalize-space()="Note"]]');
        self::assertSame(['Waiting'], $this->texts($note, '//span[contains(@class, "showcase-badge")]'));

        $page = $this->get('/_showcase/other/Other:Plain');
        self::assertSame(200, $this->client()->getResponse()->getStatusCode());
        self::assertCount(0, $page->filterXPath('//div[contains(@class, "showcase-page-head")]//span[contains(@class, "showcase-badge")]'));
    }

    public function testIndexFlagsAComponentWithNoExampleInText(): void
    {
        $crawler = $this->get('/_showcase/');

        $chart = $crawler->filterXPath('//li[a[normalize-space()="Chart"]]');
        self::assertSame(['Waiting', 'No example'], $this->texts($chart, '//span[contains(@class, "showcase-badge")]'));
        $button = $crawler->filterXPath('//li[a[normalize-space()="Button"]]');
        self::assertSame(['Ready'], $this->texts($button, '//span[contains(@class, "showcase-badge")]'));
        self::assertSame(1, substr_count((string) $this->client()->getResponse()->getContent(), 'showcase-badge-no-example'));
    }

    public function testComponentPageWithNoExampleSaysSo(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Experimental:Chart');

        self::assertSame(200, $this->client()->getResponse()->getStatusCode());
        self::assertSame(['This component has no example yet.'], $this->texts($crawler, '//p[contains(@class, "showcase-muted")]'));
        self::assertCount(0, $crawler->filterXPath('//article'));
    }

    public function testIndexListsDocumentationPagesInTheirGroup(): void
    {
        $crawler = $this->get('/_showcase/');

        $foundations = $crawler->filterXPath('//main//section[h3="Foundations"]');
        $link = $foundations->filterXPath('//a');
        self::assertSame('Colours', $link->text());
        self::assertSame('/_showcase/demo/_docs/colours', $link->attr('href'));
    }

    public function testComponentLinksKeepTheColonOfTheirName(): void
    {
        $crawler = $this->get('/_showcase/');

        self::assertSame('/_showcase/demo/Demo:Form:Input', $crawler->filterXPath('//a[normalize-space()="Input"]')->attr('href'));
        $this->get('/_showcase/demo/Demo:Form:Input');
        self::assertSame(200, $this->client()->getResponse()->getStatusCode());
    }

    public function testComponentPageShowsTheTitleThenTheLargePreviewThenCodeAndPropsWithoutTabs(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        self::assertSame(['Primary', 'Dangerous action', 'With icon', 'In a toolbar'], $this->texts($crawler, '//article/header/h3'));
        self::assertCount(0, $crawler->filterXPath('//*[@role="tab" or @role="tablist" or @role="tabpanel"]'));
        $primary = $crawler->filterXPath('//article[@id="example-primary"]');
        // In the page, in this order: title, preview, then (readable without JavaScript) the code and the props.
        self::assertSame(['header', 'div', 'details', 'details'], array_map(static fn (\DOMNode $node): string => $node->nodeName, iterator_to_array($crawler->filterXPath('//article[@id="example-primary"]/*'))));
        self::assertSame('/_showcase/demo/Demo:Button/primary/preview', $crawler->filterXPath('//article[@id="example-primary"]/div[contains(@class, "showcase-preview")]//iframe')->attr('src'));
        self::assertSame(['Code', 'Props'], $this->texts($crawler, '//article[@id="example-primary"]/details/summary'));
        self::assertStringContainsString('Demo:Button', $primary->filterXPath('.//details[@data-ux-showcase-details="code"]//pre')->text());
        self::assertGreaterThan(0, $primary->filterXPath('.//details[@data-ux-showcase-details="props"]//table//tbody/tr')->count());
    }

    public function testTheBarePageAndTheIndexHaveNoBottomBar(): void
    {
        self::assertCount(0, $this->get('/_showcase/')->filterXPath('//*[@data-ux-showcase-footer or @data-ux-showcase-panel]'));
        self::assertCount(0, $this->get('/_showcase/demo/Demo:Button/primary/preview')->filterXPath('//*[@data-ux-showcase-footer or @data-ux-showcase-panel]'));
    }

    public function testThePagesOfExamplesHaveABottomBarWithTheirOwnChoicesAndThePanelButtons(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        $footer = $crawler->filterXPath('//div[@data-ux-showcase-footer]');
        self::assertCount(1, $footer);
        self::assertSame('Primary', trim($footer->filterXPath('.//*[@data-current-name]')->text()));
        self::assertSame(['', 'responsive', 'mobile', 'tablet', 'desktop'], $this->attributes($footer, './/select[@data-override="viewport"]/option', 'value'));
        self::assertSame(['', 'none', 'light', 'white', 'dark'], $this->attributes($footer, './/select[@data-override="background"]/option', 'value'));
        self::assertSame('From the header', trim($footer->filterXPath('.//select[@data-override="viewport"]/option[1]')->text()));
        self::assertCount(1, $footer->filterXPath('.//button[@data-override-reset][@hidden]'));
        // Each button says what it opens and that it is closed; the panel is a dialog that is closed too.
        self::assertSame(['Code', 'Props'], $this->texts($footer, './/button[@data-panel-toggle]'));
        self::assertSame(['false', 'false'], $this->attributes($footer, './/button[@data-panel-toggle]', 'aria-expanded'));
        self::assertSame(['showcase-panel', 'showcase-panel'], $this->attributes($footer, './/button[@data-panel-toggle]', 'aria-controls'));
        $panel = $crawler->filterXPath('//div[@id="showcase-panel"]');
        self::assertSame('dialog', $panel->attr('role'));
        self::assertNotNull($panel->attr('hidden'));
        self::assertCount(1, $panel->filterXPath('.//button[@data-panel-close]'));
    }

    public function testADocumentationPageHasNoPropsButton(): void
    {
        $crawler = $this->get('/_showcase/demo/_docs/colours');

        self::assertSame(['Code'], $this->texts($crawler, '//div[@data-ux-showcase-footer]//button[@data-panel-toggle]'));
        self::assertCount(0, $crawler->filterXPath('//details[@data-ux-showcase-details="props"]'));
    }

    public function testThePanelClosesWithEscapeGivesFocusBackAndNamesWhatItControls(): void
    {
        $script = $this->asset((string) $this->get('/_showcase/')->filterXPath('//script[@src]')->attr('src'));

        self::assertStringContainsString("event.key === 'Escape'", $script);
        self::assertStringContainsString('opener.focus()', $script);
        self::assertStringContainsString("setAttribute('aria-expanded'", $script);
        self::assertStringContainsString('IntersectionObserver', $script);
    }

    public function testComponentPageOffersTheWidthsAndTheBackgroundsOfTheConfiguration(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        self::assertSame(['responsive', 'mobile', 'tablet', 'desktop'], $this->attributes($crawler, '//a[@data-viewport]', 'data-viewport'));
        self::assertSame(['', '375', '768', '1280'], $this->attributes($crawler, '//a[@data-viewport]', 'data-width'));
        self::assertSame(['', 'light', 'white', 'dark'], $this->attributes($crawler, '//a[@data-background]', 'data-background'));
        // The first width is the default, and no background is forced.
        self::assertSame(['responsive'], $this->attributes($crawler, '//a[@data-viewport][@aria-current]', 'data-viewport'));
        self::assertSame([''], $this->attributes($crawler, '//a[@data-background][@aria-current]', 'data-background'));
    }

    public function testTheChoicesLiveInTheQueryStringSoALinkSharesThem(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button?viewport=mobile&background=dark');

        self::assertSame(['mobile'], $this->attributes($crawler, '//a[@data-viewport][@aria-current]', 'data-viewport'));
        self::assertSame(['dark'], $this->attributes($crawler, '//a[@data-background][@aria-current]', 'data-background'));
        $frame = $crawler->filterXPath('//article[@id="example-primary"]//iframe');
        self::assertStringContainsString('width: 375px', (string) $frame->attr('style'));
        self::assertSame('/_showcase/demo/Demo:Button/primary/preview?background=dark', $frame->attr('src'));
        // A width link keeps the background, and the other way round.
        self::assertSame('/_showcase/demo/Demo:Button?viewport=tablet&background=dark', $crawler->filterXPath('//a[@data-viewport="tablet"]')->attr('href'));
        self::assertSame('/_showcase/demo/Demo:Button?viewport=mobile&background=white', $crawler->filterXPath('//a[@data-background="white"]')->attr('href'));
    }

    public function testAnUnknownChoiceFallsBackToTheDefaults(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button?viewport=huge&background=neon');

        self::assertSame(['responsive'], $this->attributes($crawler, '//a[@data-viewport][@aria-current]', 'data-viewport'));
        self::assertSame([''], $this->attributes($crawler, '//a[@data-background][@aria-current]', 'data-background'));
    }

    public function testEachExampleHasATitledFrameOnItsBarePage(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        $frame = $crawler->filterXPath('//article[@id="example-danger"]//iframe');
        self::assertSame('Preview of Dangerous action', $frame->attr('title'));
        self::assertSame('/_showcase/demo/Demo:Button/danger/preview', $frame->attr('src'));
        self::assertCount(4, $crawler->filterXPath('//iframe[@title]'));
        // The fixed height of an example is the height of its frame; the others follow their content.
        self::assertStringContainsString('height: 120px', (string) $frame->attr('style'));
        self::assertNotNull($frame->attr('data-fixed-height'));
        self::assertStringNotContainsString('height', (string) $crawler->filterXPath('//article[@id="example-primary"]//iframe')->attr('style'));
    }

    public function testCodeTabShowsTheGeneratedCodeOrTheSourceOfAFreeExample(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        self::assertSame('<twig:Demo:Button label="Save" />', $crawler->filterXPath('//article[@id="example-primary"]//pre')->text());
        self::assertStringContainsString('<div class="toolbar"><twig:Demo:Button label="One" /></div>', $crawler->filterXPath('//article[@id="example-in-a-toolbar"]//pre')->text());
    }

    public function testPropsTabShowsTheTableOfTheComponent(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Form:Input');

        $table = $crawler->filterXPath('//article[@id="example-text"]//table');
        self::assertSame(['Name', 'Type', 'Default', 'Description', 'Required', 'Allowed values'], $this->texts($table, '//thead/th | //thead//th'));
        $rows = [];
        foreach ($table->filterXPath('//tbody/tr') as $row) {
            $cells = (new Crawler($row))->filterXPath('//th | //td');
            $rows[$cells->eq(0)->text()] = array_map(static fn (int $i): string => trim($cells->eq($i)->text()), range(1, 5));
        }
        self::assertArrayHasKey('name', $rows);
        self::assertSame('Yes', $rows['name'][3]);
        self::assertSame('none', $rows['name'][1]);
        self::assertStringContainsString("'primary'", $this->get('/_showcase/demo/Demo:Button')->filterXPath('//article[@id="example-primary"]//table')->text());
    }

    public function testPropsTabListsTheBlocksOfTheComponent(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Layout:Card');

        $blocks = $crawler->filterXPath('//article[@id="example-blocks"]//ul[contains(@class, "showcase-blocks")]/li/code');
        self::assertContains('footer', array_map(static fn (\DOMNode $node): string => $node->textContent, iterator_to_array($blocks)));
    }

    public function testAFreeExampleOfAComponentHasNoPropsDetails(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        $free = $crawler->filterXPath('//article[@id="example-in-a-toolbar"]');
        self::assertSame(['Code'], $this->texts($crawler, '//article[@id="example-in-a-toolbar"]/details/summary'));
        self::assertCount(0, $free->filterXPath('//table'));
    }

    public function testADocumentationPageRendersWithoutAPropsDetails(): void
    {
        $crawler = $this->get('/_showcase/demo/_docs/colours');

        self::assertSame(200, $this->client()->getResponse()->getStatusCode());
        self::assertSame('Colours', $crawler->filterXPath('//main//h2')->text());
        self::assertSame(['Code'], $this->texts($crawler, '//article/details/summary'));
        self::assertStringContainsString('<li style="color: #0d6efd">Primary</li>', $crawler->filterXPath('//article//pre')->text());
        self::assertSame('/_showcase/demo/_docs/colours/preview', $crawler->filterXPath('//iframe')->attr('src'));
        self::assertSame('Preview of Colours', $crawler->filterXPath('//iframe')->attr('title'));
    }

    public function testADocumentationPageNamesItsExampleOnceInTheTitleOfThePage(): void
    {
        $crawler = $this->get('/_showcase/demo/_docs/colours');

        self::assertCount(0, $crawler->filterXPath('//article/header'));
        self::assertSame(['Colours'], $this->texts($crawler, '//main//*[self::h2 or self::h3][not(ancestor::nav)]'));
        self::assertSame('showcase-heading', $crawler->filterXPath('//article')->attr('aria-labelledby'));
        self::assertSame('Colours', $crawler->filterXPath('//article')->attr('data-title'));
    }

    public function testActionsAreRenderedAsButtonsWhereTheyAreVisible(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button');

        $form = $crawler->filterXPath('//div[contains(@class, "showcase-page-head")]//form');
        self::assertSame('post', $form->attr('method'));
        self::assertSame('/cms/add', $form->attr('action'));
        self::assertSame('Demo:Button', $form->filterXPath('//input[@name="component"]')->attr('value'));
        self::assertSame('Add to a page', trim($form->filterXPath('//button')->text()));
        // An example action is a plain link, once per component example, never on a free one.
        self::assertSame(['/cms/keep?example=primary', '/cms/keep?example=danger', '/cms/keep?example=with-icon'], $this->attributes($crawler, '//a[@data-action="keep-example"]', 'href'));

        $card = $this->get('/_showcase/demo/Demo:Layout:Card');
        self::assertCount(0, $card->filterXPath('//*[@data-action="add-to-page"]'));
        self::assertCount(0, $this->get('/_showcase/demo/_docs/colours')->filterXPath('//*[@data-action]'));
    }

    public function testAHostOverridesTheBlocksOfTheComponentPage(): void
    {
        $kernel = Configuration::kernel();
        $directory = $kernel->getProjectDir().'/templates/bundles/OpenStudioUxShowcaseBundle';
        (new Filesystem())->mkdir($directory);
        file_put_contents($directory.'/component.html.twig', <<<'TWIG'
            {% extends '@!OpenStudioUxShowcase/component.html.twig' %}
            {% block header_actions %}{{ parent() }}<i id="host-header">{{ library.name }}</i>{% endblock %}
            {% block below_preview %}<i class="host-preview">{{ view.example.slug }}</i>{% endblock %}
            {% block below_props %}<i class="host-props">{{ component.name }}</i>{% endblock %}
            TWIG);
        $this->kernel = $kernel;
        $kernel->boot();
        $client = $kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $crawler = $client->request('GET', '/_showcase/demo/Demo:Button');

        self::assertSame('demo', $crawler->filterXPath('//i[@id="host-header"]')->text());
        self::assertSame(['primary', 'danger', 'with-icon', 'in-a-toolbar'], $this->texts($crawler, '//i[@class="host-preview"]'));
        // Below the props table: only the examples that have one.
        self::assertSame(['Demo:Button', 'Demo:Button', 'Demo:Button'], $this->texts($crawler, '//i[@class="host-props"]'));
        self::assertCount(1, $crawler->filterXPath('//*[@data-action="add-to-page"]'), 'the default content of the block is kept by parent()');
    }

    public function testADocumentationPageStatusIsShownInTheIndexTheMenuAndThePageAndAbsentWhenNotGiven(): void
    {
        $crawler = $this->get('/_showcase/');
        self::assertSame(['Waiting', 'Documentation'], $this->texts($crawler, '//main//li[a[normalize-space()="Spacing"]]//span[contains(@class, "showcase-badge")]'));
        self::assertSame(['Documentation'], $this->texts($crawler, '//main//li[a[normalize-space()="Colours"]]//span[contains(@class, "showcase-badge")]'));
        self::assertSame(['Waiting'], $this->texts($crawler, '//nav[@id="showcase-menu"]//li[a[starts-with(normalize-space(), "Spacing")]]//span[contains(@class, "showcase-badge")]'));
        self::assertSame([], $this->texts($crawler, '//nav[@id="showcase-menu"]//li[a[starts-with(normalize-space(), "Colours")]]//span[contains(@class, "showcase-badge")]'));

        $page = $this->get('/_showcase/demo/_docs/spacing');
        self::assertSame(['Waiting'], $this->texts($page, '//div[@class="showcase-page-head"]//span[contains(@class, "showcase-badge")]'));
        self::assertSame([], $this->texts($this->get('/_showcase/demo/_docs/colours'), '//div[@class="showcase-page-head"]//span[contains(@class, "showcase-badge")]'));
    }

    public function testALongLabelOfTheMenuCanWrapBetweenItsWordsAndKeepsTheBadgeApart(): void
    {
        $crawler = $this->get('/_showcase/');

        $label = $crawler->filterXPath('//nav[@id="showcase-menu"]//a[contains(@href, "CompanyBuyers")]/span[contains(@class, "showcase-menu-label")]');
        self::assertCount(1, $label);
        self::assertSame('Company<wbr>Buyers<wbr>Account<wbr>Overview<wbr>Summary', $label->html());
        $style = $this->asset((string) $crawler->filterXPath('//link[@rel="stylesheet"]')->attr('href'));
        self::assertMatchesRegularExpression('/\.showcase-menu-label \{[^}]*min-width: 0;[^}]*overflow-wrap: anywhere;/', $style);
        self::assertMatchesRegularExpression('/\.showcase-menu \.showcase-badge \{[^}]*flex: none;/', $style);
    }

    public function testTheBarePageGivesTheScrollbarGutterBackToTheDocument(): void
    {
        $this->get('/_showcase/demo/Demo:Button/primary/preview');

        $html = (string) $this->client()->getResponse()->getContent();
        self::assertMatchesRegularExpression('#<style data-ux-showcase>html \{ overflow-y: auto !important; scrollbar-gutter: auto !important; \}</style>\s*</head>#', $html);
    }

    public function testTheWidthAndBackgroundChoicesAreInTheHeaderOfPagesWithPreviewsOnly(): void
    {
        foreach (['/_showcase/demo/Demo:Button', '/_showcase/demo/_docs/colours'] as $path) {
            $crawler = $this->get($path);
            self::assertCount(1, $crawler->filterXPath('//header[contains(@class, "showcase-header")]//*[@data-ux-showcase-controls]'), $path);
            self::assertCount(0, $crawler->filterXPath('//main//*[@data-ux-showcase-controls]'), $path);
        }
        $index = $this->get('/_showcase/');
        self::assertCount(0, $index->filterXPath('//*[@data-ux-showcase-controls]'));
    }

    public function testTheChoicesFollowTheVisitorToTheOtherPagesThroughTheLinks(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button?viewport=mobile&background=dark');

        $menu = $crawler->filterXPath('//nav[@id="showcase-menu"]');
        self::assertSame('/_showcase/demo/Demo:Form:Input?viewport=mobile&background=dark', $menu->filterXPath('//a[starts-with(normalize-space(), "Input")]')->attr('href'));
        self::assertSame('/_showcase/demo/_docs/colours?viewport=mobile&background=dark', $menu->filterXPath('//a[normalize-space()="Colours"]')->attr('href'));
        self::assertSame('/_showcase/?viewport=mobile&background=dark', $menu->filterXPath('//a[@class="showcase-menu-home"]')->attr('href'));
        // The index has no choices of its own, and still passes them on; the defaults add nothing to the addresses.
        self::assertSame('/_showcase/demo/Demo:Form:Input?background=dark', $this->get('/_showcase/?background=dark')->filterXPath('//main//a[starts-with(normalize-space(), "Input")]')->attr('href'));
        self::assertSame('/_showcase/demo/Demo:Form:Input', $this->get('/_showcase/demo/Demo:Button')->filterXPath('//nav//a[starts-with(normalize-space(), "Input")]')->attr('href'));
    }

    public function testBarePageRendersTheExampleInTheTemplateOfTheLibrary(): void
    {
        $crawler = $this->get('/_showcase/demo/Demo:Button/primary/preview');

        $response = $this->client()->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        // The head and the body attributes come from the template of the library, the example sits in the wrapper.
        self::assertSame('/demo-theme.css', $crawler->filterXPath('//head/link[@rel="stylesheet"]')->attr('href'));
        self::assertSame('1', $crawler->filterXPath('//body')->attr('data-demo-theme'));
        self::assertSame('Save', trim($crawler->filterXPath('//div[@class="demo-wrapper"]/button')->text()));
        self::assertSame('Primary', $crawler->filterXPath('//title')->text());
        // Nothing of the showcase screen around it.
        self::assertCount(0, $crawler->filterXPath('//*[@data-ux-showcase-footer]'));
    }

    public function testBarePageCarriesTheHeightScriptAfterTheContent(): void
    {
        $this->get('/_showcase/demo/Demo:Button/primary/preview');

        $html = (string) $this->client()->getResponse()->getContent();
        self::assertSame(1, preg_match('#<script src="(/_showcase/_assets/bare\.js\?v=[0-9a-f]{8})" defer></script>\s*</body>#', $html, $matches));
        $this->client()->request('GET', $matches[1] ?? self::fail('no script injected'));
        self::assertSame(200, $this->client()->getResponse()->getStatusCode());
        self::assertStringContainsString('ux-showcase:frame', (string) $this->client()->getResponse()->getContent());
        self::assertSame(1, substr_count($html, '<script'));
    }

    public function testBarePageAppliesTheHtmlAttributesOfTheBackground(): void
    {
        $kernel = Configuration::kernel(configuration: Configuration::demo() + ['backgrounds' => [
            'light' => '#f8f9fa',
            'night' => ['color' => '#212529', 'html_attributes' => ['data-bs-theme' => 'dark']],
        ]]);
        $this->kernel = $kernel;
        $kernel->boot();
        $client = $kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $html = $client->request('GET', '/_showcase/demo/Demo:Button/primary/preview?background=night')->filterXPath('//html');
        self::assertSame('dark', $html->attr('data-bs-theme'));
        self::assertSame('background-color: #212529', $html->attr('style'));
        self::assertSame('night', $html->attr('data-ux-showcase-background'));

        $html = $client->request('GET', '/_showcase/demo/Demo:Button/primary/preview?background=light')->filterXPath('//html');
        self::assertNull($html->attr('data-bs-theme'));
        self::assertSame('background-color: #f8f9fa', $html->attr('style'));
    }

    public function testBarePageBackgroundComesFromTheScreenChoiceThenTheExample(): void
    {
        $html = $this->get('/_showcase/demo/Demo:Button/danger/preview')->filterXPath('//html');
        self::assertSame('dark', $html->attr('data-ux-showcase-background'), 'the option of the example');

        $html = $this->get('/_showcase/demo/Demo:Button/danger/preview?background=white')->filterXPath('//html');
        self::assertSame('white', $html->attr('data-ux-showcase-background'), 'the screen choice wins');
        self::assertSame('background-color: #ffffff', $html->attr('style'));

        $html = $this->get('/_showcase/demo/Demo:Button/primary/preview')->filterXPath('//html');
        self::assertNull($html->attr('data-ux-showcase-background'));
        self::assertNull($html->attr('style'));
    }

    public function testBarePageSwitchesTheProfilerOffButScreensKeepIt(): void
    {
        $this->get('/_showcase/');
        self::assertTrue($this->client()->getResponse()->headers->has('X-Debug-Token'), 'the screens are profiled, so the check below proves something');

        $this->get('/_showcase/demo/Demo:Button/primary/preview');
        self::assertFalse($this->client()->getResponse()->headers->has('X-Debug-Token'));
        self::assertFalse($this->client()->getResponse()->headers->has('X-Debug-Token-Link'));

        $this->get('/_showcase/demo/_docs/colours/preview');
        self::assertFalse($this->client()->getResponse()->headers->has('X-Debug-Token'));
    }

    public function testADocumentationPageHasABarePage(): void
    {
        $crawler = $this->get('/_showcase/demo/_docs/colours/preview');

        self::assertSame(200, $this->client()->getResponse()->getStatusCode());
        self::assertSame('Primary', $crawler->filterXPath('//ul[@class="colours"]/li')->text());
        self::assertStringContainsString('bare.js', (string) $this->client()->getResponse()->getContent());
    }

    public function testBarePageUsesTheBundleTemplateWhenTheLibraryHasNone(): void
    {
        $kernel = Configuration::kernel(configuration: Configuration::demo(['bare' => null]));
        $this->kernel = $kernel;
        $kernel->boot();
        $client = $kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $crawler = $client->request('GET', '/_showcase/demo/Demo:Button/primary/preview');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filterXPath('//head/link'));
        self::assertNull($crawler->filterXPath('//body')->attr('data-demo-theme'));
        self::assertSame('Save', trim($crawler->filterXPath('//button')->text()));
    }

    public function testBareTemplateWithoutTheImposedBlocksIsRefusedWithAClearMessage(): void
    {
        $kernel = Configuration::kernel(configuration: Configuration::demo(['bare' => 'showcase/bare-broken.html.twig']));
        $this->kernel = $kernel;
        $kernel->boot();
        $client = $kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $client->request('GET', '/_showcase/demo/Demo:Button/primary/preview');

        self::assertSame(500, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('has no block "head_assets"', (string) $client->getResponse()->getContent());
    }

    public function testScriptsAndStylesAreServedByTheBundleWithCacheHeaders(): void
    {
        $crawler = $this->get('/_showcase/');
        $script = $crawler->filterXPath('//script[@src]')->attr('src');
        $style = $crawler->filterXPath('//link[@rel="stylesheet"]')->attr('href');
        self::assertCount(0, $crawler->filterXPath('//script[not(@src)]'), 'no inline script');

        $this->client()->request('GET', (string) $script);
        $response = $this->client()->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('data-ux-showcase-footer', (string) $response->getContent());
        $etag = (string) $response->headers->get('ETag');
        self::assertNotSame('', $etag);

        $this->client()->request('GET', (string) $script, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(304, $this->client()->getResponse()->getStatusCode());

        $this->client()->request('GET', (string) $style);
        self::assertStringStartsWith('text/css', (string) $this->client()->getResponse()->headers->get('Content-Type'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownAddresses(): iterable
    {
        yield 'library' => ['/_showcase/nope/Demo:Button'];
        yield 'component' => ['/_showcase/demo/Demo:Nope'];
        yield 'hidden component' => ['/_showcase/demo/Demo:Legacy:Old'];
        yield 'library of a preview' => ['/_showcase/nope/Demo:Button/primary/preview'];
        yield 'component of a preview' => ['/_showcase/demo/Demo:Nope/primary/preview'];
        yield 'slug of a preview' => ['/_showcase/demo/Demo:Button/nope/preview'];
        yield 'slug of a component of another library' => ['/_showcase/other/Demo:Button/primary/preview'];
        yield 'documentation page' => ['/_showcase/demo/_docs/nope'];
        yield 'library of a documentation page' => ['/_showcase/nope/_docs/colours'];
        yield 'preview of a documentation page' => ['/_showcase/demo/_docs/nope/preview'];
        yield 'documentation page as an example of a component' => ['/_showcase/demo/Demo:Button/colours/preview'];
        yield 'asset' => ['/_showcase/_assets/nope.js'];
    }

    #[DataProvider('unknownAddresses')]
    public function testAnUnknownLibraryComponentOrExampleIsNotFound(string $path): void
    {
        $this->get($path);

        self::assertSame(404, $this->client()->getResponse()->getStatusCode());
        self::assertSame('noindex, nofollow', $this->client()->getResponse()->headers->get('X-Robots-Tag'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyRoute(): iterable
    {
        yield 'index' => ['/_showcase/'];
        yield 'component' => ['/_showcase/demo/Demo:Button'];
        yield 'documentation page' => ['/_showcase/demo/_docs/colours'];
        yield 'preview' => ['/_showcase/demo/Demo:Button/primary/preview'];
        yield 'documentation preview' => ['/_showcase/demo/_docs/colours/preview'];
        yield 'asset' => ['/_showcase/_assets/showcase.js'];
    }

    #[DataProvider('everyRoute')]
    public function testEveryRouteIsNotFoundOutsideDebug(string $path): void
    {
        $this->kernel = Configuration::kernel(debug: false);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $client->request('GET', $path);

        self::assertSame(404, $client->getResponse()->getStatusCode());
        self::assertSame('noindex, nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertStringNotContainsString('showcase-main', (string) $client->getResponse()->getContent());
    }

    public function testEveryRouteOfTheBundleIsFlaggedForTheAccessCheck(): void
    {
        $this->client();
        $router = $this->kernel?->getContainer()->get('router');
        self::assertInstanceOf(\Symfony\Component\Routing\RouterInterface::class, $router);

        $names = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, 'ux_showcase_')) {
                $names[] = $name;
                self::assertTrue($route->getDefault('_ux_showcase'), $name);
            }
        }
        $expected = ['ux_showcase_index', 'ux_showcase_component', 'ux_showcase_doc', 'ux_showcase_preview', 'ux_showcase_preview_try', 'ux_showcase_preview_link', 'ux_showcase_doc_preview', 'ux_showcase_asset'];
        self::assertSame($expected, array_values(array_intersect($expected, $names)));
        self::assertCount(8, $names);
    }

    private function asset(string $url): string
    {
        $this->client()->request('GET', $url);

        return (string) $this->client()->getResponse()->getContent();
    }

    private function client(): KernelBrowser
    {
        if (null === $this->kernel) {
            $this->kernel = Configuration::kernel();
            $this->kernel->boot();
        }
        if (null === $this->client) {
            $client = $this->kernel->getContainer()->get('test.client');
            self::assertInstanceOf(KernelBrowser::class, $client);
            $this->client = $client;
        }

        return $this->client;
    }

    private function get(string $path): Crawler
    {
        return $this->client()->request('GET', $path);
    }

    /**
     * @return list<string>
     */
    private function texts(Crawler $crawler, string $xpath): array
    {
        return array_values(array_map(static fn (\DOMNode $node): string => trim($node->textContent), iterator_to_array($crawler->filterXPath($xpath))));
    }

    /**
     * @return list<string>
     */
    private function attributes(Crawler $crawler, string $xpath, string $attribute): array
    {
        return array_map(static fn (\DOMElement $node): string => $node->getAttribute($attribute), array_values(array_filter(iterator_to_array($crawler->filterXPath($xpath)), static fn (\DOMNode $node): bool => $node instanceof \DOMElement)));
    }
}
