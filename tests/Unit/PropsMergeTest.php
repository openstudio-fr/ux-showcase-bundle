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
use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use PHPUnit\Framework\TestCase;

final class PropsMergeTest extends TestCase
{
    public function testRequiredIsNoDefaultAndNoNullableType(): void
    {
        self::assertTrue(PropDoc::create('a', 'string')->required);
        self::assertTrue(PropDoc::create('a')->required, 'an unknown type is not nullable');
        self::assertFalse(PropDoc::create('a', 'string', true, 'x')->required);
        self::assertFalse(PropDoc::create('a', '?string')->required);
        self::assertFalse(PropDoc::create('a', 'int|null')->required);
        self::assertFalse(PropDoc::create('a', 'null|int|string')->required);
        self::assertTrue(PropDoc::create('a', 'nullable_name')->required);
        self::assertTrue(PropDoc::create('a', 'list<null>')->required);
    }

    public function testTheFirstNonEmptyDescriptionAndAllowedValuesWin(): void
    {
        $merged = PropDoc::create('a', 'string', false, null, 'first', ['x'])->mergedWith(PropDoc::create('a', 'string', false, null, 'second', ['y']));

        self::assertSame('first', $merged->description);
        self::assertSame(['x'], $merged->allowedValues);

        $merged = PropDoc::create('a')->mergedWith(PropDoc::create('a', 'string', false, null, 'second', ['y']));
        self::assertSame('second', $merged->description);
        self::assertSame(['y'], $merged->allowedValues);
    }

    public function testTheLaterKnownTypeAndDefaultWin(): void
    {
        $template = PropDoc::create('a', 'html', true, 'from template', '', null, ['doc-comment']);
        $class = PropDoc::create('a', 'string', true, 'from class', '', null, ['property']);

        $merged = $template->mergedWith($class);
        self::assertSame('string', $merged->type);
        self::assertSame('from class', $merged->default);
        self::assertSame(['doc-comment', 'property'], $merged->sources);

        $merged = $template->mergedWith(PropDoc::create('a'));
        self::assertSame('html', $merged->type, 'an unknown type does not erase a known one');
        self::assertSame('from template', $merged->default);
    }

    public function testRequiredIsWorkedOutAgainAfterTheMerge(): void
    {
        $tag = PropDoc::create('a', 'mixed', false);
        self::assertTrue($tag->required);

        self::assertFalse($tag->mergedWith(PropDoc::create('a', '?string'))->required);
        self::assertFalse($tag->mergedWith(PropDoc::create('a', 'string', true, ''))->required);
        self::assertTrue($tag->mergedWith(PropDoc::create('a', 'string'))->required);
    }

    public function testComponentsMergeInTheOrderOfTheFirstReader(): void
    {
        $first = new ComponentDoc('C', '', [PropDoc::create('b'), PropDoc::create('a')], [new BlockDoc('x', ''), new BlockDoc('y', 'why')]);
        $second = new ComponentDoc('C', 'About', [PropDoc::create('c'), PropDoc::create('a', 'int')], [new BlockDoc('x', 'ex'), new BlockDoc('y', 'other')]);

        $merged = $first->mergedWith($second);

        self::assertSame('About', $merged->description);
        self::assertSame(['b', 'a', 'c'], array_map(static fn (PropDoc $prop): string => $prop->name, $merged->props));
        self::assertSame('int', $merged->prop('a')?->type);
        self::assertNull($merged->prop('z'));
        self::assertEquals([new BlockDoc('x', 'ex'), new BlockDoc('y', 'why')], $merged->blocks);
    }

    public function testFieldsOfAListAddUp(): void
    {
        $one = PropDoc::create('rows', fields: [PropDoc::create('name', 'string')]);
        $two = PropDoc::create('rows', fields: [PropDoc::create('name', 'string', false, null, 'Name'), PropDoc::create('id', 'int')]);

        $fields = $one->mergedWith($two)->fields;

        self::assertSame(['name', 'id'], array_map(static fn (PropDoc $field): string => $field->name, $fields));
        self::assertSame('Name', $fields[0]->description);
    }
}
