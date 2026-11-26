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

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Example\ExampleRenderer;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ExampleRenderingTest extends TestCase
{
    private ?TestKernel $kernel = null;
    private ShowcaseRegistry $registry;
    private ExampleRenderer $renderer;
    private ExampleCodeGenerator $codeGenerator;

    protected function setUp(): void
    {
        $this->kernel = Configuration::kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $registry = $container->get(ShowcaseRegistry::class);
        $renderer = $container->get(ExampleRenderer::class);
        $codeGenerator = $container->get(ExampleCodeGenerator::class);
        \assert($registry instanceof ShowcaseRegistry && $renderer instanceof ExampleRenderer && $codeGenerator instanceof ExampleCodeGenerator);
        [$this->registry, $this->renderer, $this->codeGenerator] = [$registry, $renderer, $codeGenerator];

        // A live component renders from a request, as it does in the showcase.
        $requests = $container->get('request_stack');
        \assert($requests instanceof \Symfony\Component\HttpFoundation\RequestStack);
        $requests->push(Request::create('/'));
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testATwigExampleRendersInsideTheWrapper(): void
    {
        $html = $this->render('Demo:Button', 'primary');

        self::assertStringStartsWith('<div class="demo-wrapper" data-controller="modal-root" data-library="demo">', $html);
        self::assertStringContainsString('<button class="btn btn-primary">Save</button>', $html);
    }

    public function testTheWrapperIsDisabledByTheExample(): void
    {
        $html = $this->render('Demo:Button', 'with-icon');

        self::assertStringNotContainsString('demo-wrapper', $html);
        self::assertStringContainsString('<button class="btn btn-primary">Send<span class="icon">&rarr;</span>', $html);
    }

    public function testTheWrapperIsDisabledByTheScreenChoice(): void
    {
        $html = $this->render('Demo:Button', 'primary', new PreviewOptions(wrapper: false));

        self::assertStringNotContainsString('demo-wrapper', $html);
        self::assertStringContainsString('btn-primary', $html);
    }

    public function testTheScreenChoiceCanTurnTheWrapperBackOn(): void
    {
        self::assertStringContainsString('demo-wrapper', $this->render('Demo:Button', 'with-icon', new PreviewOptions(wrapper: true)));
    }

    public function testAPhpExampleFedByAJsonFixtureRendersItsNestedData(): void
    {
        $html = $this->render('Demo:Layout:Card', 'article');

        self::assertStringContainsString('<h2>From a fixture</h2>', $html);
        self::assertStringContainsString('<li>Book 12.5</li><li>Pen 1.2</li>', $html);
    }

    public function testBlocksOfAnExampleReachTheComponent(): void
    {
        $html = $this->render('Demo:Layout:Card', 'blocks');

        self::assertStringContainsString('<p>Body</p>', $html);
        self::assertStringContainsString('<footer>Footer</footer>', $html);
    }

    public function testALiveComponentRenders(): void
    {
        $html = $this->render('Demo:Form:Counter', 'start');

        self::assertStringContainsString('count=3', $html);
        self::assertStringContainsString('data-live-name-value="Demo:Form:Counter"', $html);
    }

    public function testAnAnonymousComponentWithQuotesAndBackslashesInItsProps(): void
    {
        $html = $this->render('Demo:Form:Input', 'text');

        self::assertStringContainsString('<input name="email" value="a &quot;quoted&quot; \ value" disabled>', $html);
    }

    public function testADocumentationPageRendersAsATwigSnippet(): void
    {
        $page = $this->registry->findExample('demo', null, 'colours');
        self::assertNotNull($page);

        $html = $this->renderer->render($this->library(), $page);

        self::assertStringContainsString('<ul class="colours">', $html);
        self::assertStringContainsString('demo-wrapper', $html);
        self::assertStringNotContainsString('{%', $html);
    }

    public function testAnExampleOfAnOtherLibraryWithoutWrapperRendersBare(): void
    {
        $library = $this->registry->library('other');
        $example = $this->registry->findExample('other', 'Other:Note', 'hello');
        self::assertNotNull($library);
        self::assertNotNull($example);

        self::assertSame('<aside>Hello</aside>', trim($this->renderer->render($library, $example)));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function generatedCode(): iterable
    {
        yield 'a prop' => ['Demo:Button', 'primary', '<twig:Demo:Button label="Save" />'];
        yield 'props in declared order' => ['Demo:Button', 'danger', '<twig:Demo:Button label="Delete" variant="danger" />'];
        yield 'content' => ['Demo:Button', 'with-icon', "<twig:Demo:Button label=\"Send\">\n    <span class=\"icon\">&rarr;</span>\n</twig:Demo:Button>"];
        yield 'quotes, backslash and booleans as expressions' => ['Demo:Form:Input', 'text', '<twig:Demo:Form:Input name="email" :value="\'a \x22quoted\x22 \\\\ value\'" :disabled="true" />'];
        yield 'nested data over several lines' => ['Demo:Layout:Card', 'article', <<<'TWIG'
            <twig:Demo:Layout:Card
                title="From a fixture"
                :items="[{ label: 'Book', meta: { price: 12.5 } }, { label: 'Pen', meta: { price: 1.2 } }]"
            />
            TWIG];
        yield 'blocks' => ['Demo:Layout:Card', 'blocks', <<<'TWIG'
            <twig:Demo:Layout:Card title="Plain" :items="[]">
                <p>Body</p>
                <twig:block name="footer">Footer</twig:block>
            </twig:Demo:Layout:Card>
            TWIG];
    }

    #[DataProvider('generatedCode')]
    public function testGeneratedCode(string $component, string $slug, string $expected): void
    {
        $example = $this->registry->findExample('demo', $component, $slug);
        self::assertNotNull($example);

        self::assertSame($expected, $this->codeGenerator->generate($example));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function examplesWithCode(): iterable
    {
        foreach (self::generatedCode() as $name => [$component, $slug]) {
            yield $name => [$component, $slug];
        }
    }

    #[DataProvider('examplesWithCode')]
    public function testGeneratedCodeRendersWhatTheExampleRenders(string $component, string $slug): void
    {
        $example = $this->registry->findExample('demo', $component, $slug);
        self::assertNotNull($example);
        $bare = new PreviewOptions(wrapper: false);

        $viaCode = $this->renderer->render($this->library(), new Example($component, 'code', source: $this->codeGenerator->generate($example)), $bare);

        self::assertSame(trim($this->renderer->render($this->library(), $example, $bare)), trim($viaCode));
    }

    public function testTheCodeOfAFreeExampleIsItsSource(): void
    {
        $page = $this->registry->findExample('demo', null, 'colours');
        self::assertNotNull($page);

        self::assertSame($page->source, $this->codeGenerator->generate($page));
    }

    private function render(string $component, string $slug, PreviewOptions $screen = new PreviewOptions()): string
    {
        $example = $this->registry->findExample('demo', $component, $slug);
        self::assertNotNull($example);

        return $this->renderer->render($this->library(), $example, $screen);
    }

    private function library(): \OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface
    {
        $library = $this->registry->library('demo');
        self::assertNotNull($library);

        return $library;
    }
}
