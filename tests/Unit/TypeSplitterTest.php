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

use OpenStudio\UxShowcaseBundle\Props\TypeSplitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypeSplitterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function texts(): iterable
    {
        yield 'one word type' => ['string The label', 'string', 'The label'];
        yield 'type alone' => ['int', 'int', ''];
        yield 'generic with a space' => ['array<string, int> Quantity by size', 'array<string, int>', 'Quantity by size'];
        yield 'shape' => ['list<array{name: string, id: int}> The rows', 'list<array{name: string, id: int}>', 'The rows'];
        yield 'union with spaces' => ['string | null The label', 'string|null', 'The label'];
        yield 'nullable' => ['?string  Hint', '?string', 'Hint'];
        yield 'several lines' => ["string The label\n   continued", 'string', 'The label continued'];
        yield 'unclosed generic is the first word' => ['list<string The label', 'list<string', 'The label'];
    }

    #[DataProvider('texts')]
    public function testSplit(string $text, string $type, string $description): void
    {
        self::assertSame([$type, $description], TypeSplitter::split($text));
    }
}
