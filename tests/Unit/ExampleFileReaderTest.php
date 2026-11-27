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

use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;
use OpenStudio\UxShowcaseBundle\Twig\ExampleFileReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExampleFileReaderTest extends TestCase
{
    public function testReadsEveryDataPartOfAnExample(): void
    {
        $examples = (new ExampleFileReader())->read(<<<'TWIG'
            {% showcase_example 'full' titled 'Everything' with { label: 'Save', count: -3, ratio: 1.5, on: true, off: false, none: null, list: [1, 'two'], deep: { 'a-b': { c: 1 } } } options { background: 'dark', body_class: 'p-4', wrapper: false, height: 200 } %}
                <b>bold</b>
                  <i>indented</i>
            {% end_showcase_example %}
            TWIG, 'file.twig', 'Demo:Button');

        self::assertCount(1, $examples);
        $example = $examples[0];
        self::assertSame('Demo:Button', $example->component);
        self::assertSame('Everything', $example->title);
        self::assertSame(
            ['label' => 'Save', 'count' => -3, 'ratio' => 1.5, 'on' => true, 'off' => false, 'none' => null, 'list' => [1, 'two'], 'deep' => ['a-b' => ['c' => 1]]],
            $example->props,
        );
        self::assertSame("<b>bold</b>\n  <i>indented</i>", $example->content);
        self::assertSame('dark', $example->preview->background);
        self::assertSame('p-4', $example->preview->bodyClass);
        self::assertFalse($example->preview->wrapper);
        self::assertSame(200, $example->preview->height);
    }

    public function testAFreeExampleOfADocumentationPageReadsItsStatus(): void
    {
        $reader = new ExampleFileReader();
        $pages = $reader->read("{% showcase_free 'one' status 'ready' %}x{% end_showcase_free %}{% showcase_free 'two' titled 'Two' %}y{% end_showcase_free %}", 'file.twig', null, 'Docs');

        self::assertSame(ComponentStatus::Ready, $pages[0]->status);
        self::assertNull($pages[1]->status);
        $this->expectExceptionMessage('"status" expects');
        $reader->read("{% showcase_free 'one' status 'hidden' %}x{% end_showcase_free %}", 'file.twig', null, 'Docs');
    }

    public function testABodyMayHoldTwigThatThePrivateEnvironmentDoesNotKnow(): void
    {
        $examples = (new ExampleFileReader())->read("{% showcase_free 'page' %}{{ unknown_function(1)|unknown_filter }}{% end_showcase_free %}", 'file.twig', 'Demo:Button');

        self::assertSame('{{ unknown_function(1)|unknown_filter }}', $examples[0]->source);
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFilesFailWithAMessageThatNamesTheProblem(string $source, string $message): void
    {
        $this->expectException(ShowcaseException::class);
        $this->expectExceptionMessage($message);
        (new ExampleFileReader())->read($source, 'file.twig', 'Demo:Button');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'a variable as a prop' => ["{% showcase_example 'a' with { label: name } %}{% end_showcase_example %}", 'Props and options are literals'];
        yield 'a computed prop' => ["{% showcase_example 'a' with { count: 1 + 2 } %}{% end_showcase_example %}", 'Props and options are literals'];
        yield 'a list instead of props' => ["{% showcase_example 'a' with [1, 2] %}{% end_showcase_example %}", '"with" expects a hash of props'];
        yield 'an unknown option' => ["{% showcase_example 'a' options { colour: 'red' } %}{% end_showcase_example %}", 'Unknown option "colour"'];
        yield 'an option of the wrong type' => ["{% showcase_example 'a' options { wrapper: 'no' } %}{% end_showcase_example %}", 'The option "wrapper" expects a boolean'];
        yield 'an unknown keyword' => ["{% showcase_example 'a' having { } %}{% end_showcase_example %}", 'Unexpected "having"'];
        yield 'props on a free example' => ["{% showcase_free 'a' with { x: 1 } %}x{% end_showcase_free %}", 'Unexpected "with"'];
        yield 'a part twice' => ["{% showcase_example 'a' titled 'A' titled 'B' %}{% end_showcase_example %}", 'Unexpected "titled"'];
        yield 'a slug that is not a literal' => ['{% showcase_example slug %}{% end_showcase_example %}', 'The slug of "showcase_example" must be a string literal'];
        yield 'an invalid slug' => ["{% showcase_example 'Not Valid' %}{% end_showcase_example %}", 'The example slug "Not Valid" is not valid'];
        yield 'a free example without body' => ["{% showcase_free 'a' %}{% end_showcase_free %}", 'the free example "a" has no body'];
        yield 'a tag that is never closed' => ["{% showcase_example 'a' %}", 'not a complete showcase_example'];
        yield 'an error line' => ["\n\n{% showcase_example 'a' with { label: name } %}{% end_showcase_example %}", 'line 3'];
    }

    public function testADocumentationFileAcceptsFreeExamplesOnly(): void
    {
        $this->expectException(ShowcaseException::class);
        $this->expectExceptionMessage('a documentation page holds showcase_free tags only');
        (new ExampleFileReader())->read("{% showcase_example 'a' %}{% end_showcase_example %}", 'docs.twig', null, 'Foundations');
    }
}
