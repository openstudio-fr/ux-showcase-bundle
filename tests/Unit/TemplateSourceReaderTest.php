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

use OpenStudio\UxShowcaseBundle\Props\BlockDoc;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Props\TemplateSourceReader;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

final class TemplateSourceReaderTest extends TestCase
{
    public function testDefaultsAreReadAsLiterals(): void
    {
        $doc = $this->read("{% props a = 1, b = 2.5, c = true, d = null, e = {k: 'v', 2: [1, [2]]}, f = -1, g = 'x', h = [], i = false %}");

        self::assertSame(
            ['a' => 1, 'b' => 2.5, 'c' => true, 'd' => null, 'e' => ['k' => 'v', 2 => [1, [2]]], 'f' => -1, 'g' => 'x', 'h' => [], 'i' => false],
            array_column(array_map(static fn (PropDoc $prop): array => ['name' => $prop->name, 'default' => $prop->default], $doc->props), 'default', 'name'),
        );
        foreach ($doc->props as $prop) {
            self::assertTrue($prop->hasDefault, $prop->name);
        }
    }

    public function testADefaultThatIsNotALiteralHasADefaultAndNoValue(): void
    {
        $doc = $this->read("{% props year = 'now'|date('Y'), path = a.b, other = 1 + 2, last %}");

        foreach (['year', 'path', 'other'] as $name) {
            self::assertTrue($doc->prop($name)?->hasDefault, $name);
            self::assertNull($doc->prop($name)->default, $name);
            self::assertFalse($doc->prop($name)->required, $name);
        }
        self::assertTrue($doc->prop('last')?->required);
    }

    public function testADocumentationCommentDescribesTheNextPropOnly(): void
    {
        $doc = $this->read("{% props\n    ## string First\n    a,\n    b,\n    ## ?int Third, on\n    ## two lines\n    c = null\n%}");
        [$a, $b, $c] = [$doc->prop('a'), $doc->prop('b'), $doc->prop('c')];
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotNull($c);

        self::assertSame(['string', 'First'], [$a->type, $a->description]);
        self::assertSame(['mixed', ''], [$b->type, $b->description]);
        self::assertSame(['?int', 'Third, on two lines'], [$c->type, $c->description]);
        self::assertSame([PropDoc::SOURCE_PROPS_TAG, PropDoc::SOURCE_DOC_COMMENT], $a->sources);
        self::assertSame([PropDoc::SOURCE_PROPS_TAG], $b->sources);
    }

    public function testAnAnnotationAndTheTagOfTheSamePropAreMerged(): void
    {
        $doc = $this->read('{# @prop size ?int How big #}{% props size = 3 %}');

        self::assertEquals(PropDoc::create('size', '?int', true, 3, 'How big', null, [PropDoc::SOURCE_PROPS_TAG, PropDoc::SOURCE_ANNOTATION]), $doc->prop('size'));
    }

    public function testAnAnnotationWithoutATagIsAProp(): void
    {
        $doc = $this->read('{#- @prop only string From the class -#}');

        self::assertEquals(PropDoc::create('only', 'string', false, null, 'From the class', null, [PropDoc::SOURCE_ANNOTATION]), $doc->prop('only'));
    }

    public function testDescriptionAndBlocks(): void
    {
        $doc = $this->read("{# @description A box,\n   on two lines. #}{# @block head Above #}{# @block foot #}{% block body %}{% endblock %}{% block head %}{% endblock %}{% block body %}{% endblock %}");

        self::assertSame('A box, on two lines.', $doc->description);
        self::assertEquals([new BlockDoc('body'), new BlockDoc('head', 'Above'), new BlockDoc('foot')], $doc->blocks);
    }

    public function testAPlainCommentIsNotAnAnnotation(): void
    {
        $doc = $this->read('{# prop a string no #}{# @propx a string no #}{% props a %}');

        self::assertSame('mixed', $doc->prop('a')?->type);
    }

    private function read(string $template): \OpenStudio\UxShowcaseBundle\Props\ComponentDoc
    {
        return (new TemplateSourceReader(new Environment(new ArrayLoader())))->read('T', new Source($template, 'T'));
    }
}
