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
use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testTheTitleDefaultsToTheSlug(): void
    {
        self::assertSame('Large with icon', (new Example('Demo:Button', 'large-with-icon'))->title);
    }

    public function testPropsMustBePlainData(): void
    {
        $this->expectExceptionMessage('holds a stdClass: props are plain data');
        new Example('Demo:Button', 'a', props: ['user' => new \stdClass()]);
    }

    public function testANestedObjectIsRefusedToo(): void
    {
        $this->expectExceptionMessage('key "inner"');
        new Example('Demo:Button', 'a', props: ['outer' => ['inner' => new \stdClass()]]);
    }

    public function testAFreeExampleTakesNoProps(): void
    {
        $this->expectExceptionMessage('takes neither props nor content');
        new Example('Demo:Button', 'a', props: ['x' => 1], source: 'x');
    }

    public function testADocumentationPageNeedsAGroup(): void
    {
        $this->expectExceptionMessage('documentation page, which needs a source and a group');
        new Example(null, 'a', source: 'x');
    }

    public function testOnlyADocumentationPageHasAGroup(): void
    {
        $this->expectExceptionMessage('only a documentation page has a group');
        new Example('Demo:Button', 'a', group: 'Foundations');
    }

    public function testADocumentationPageMayHaveAStatusAndHasNoneByDefault(): void
    {
        self::assertSame(ComponentStatus::Waiting, (new Example(null, 'a', source: 'x', group: 'Foundations', status: ComponentStatus::Waiting))->status);
        self::assertNull((new Example(null, 'a', source: 'x', group: 'Foundations'))->status);
    }

    public function testOnlyADocumentationPageHasAStatus(): void
    {
        $this->expectExceptionMessage('only a documentation page has a status');
        new Example('Demo:Button', 'a', status: ComponentStatus::Ready);
    }

    public function testADocumentationPageCannotBeHidden(): void
    {
        $this->expectExceptionMessage('cannot be hidden');
        new Example(null, 'a', source: 'x', group: 'Foundations', status: ComponentStatus::Hidden);
    }

    public function testLiteralsOfTheGeneratedCode(): void
    {
        self::assertSame("{ a: 1, 'k-1': [1.0, 2.5, true, null], e: [] }", ExampleCodeGenerator::literal(['a' => 1, 'k-1' => [1.0, 2.5, true, null], 'e' => []]));
        self::assertSame("'it\\'s\\ntwo lines'", ExampleCodeGenerator::literal("it's\ntwo lines"));
    }
}
