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

use OpenStudio\UxShowcaseBundle\Example\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class FixtureLoaderTest extends TestCase
{
    public function testLoadsANestedObjectAsAnArray(): void
    {
        $data = (new FixtureLoader(__DIR__.'/../Fixtures/Library/fixtures'))->load('cards/article.json');

        $items = $data['items'] ?? null;
        self::assertIsArray($items);
        self::assertSame(['label' => 'Book', 'meta' => ['price' => 12.5]], $items[0]);
    }

    public function testRefusesAPathThatLeavesTheDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FixtureLoader(__DIR__.'/../Fixtures/Library/fixtures'))->load('../Configuration.php');
    }

    public function testNamesAMissingFixture(): void
    {
        $this->expectExceptionMessage('The fixture "nope.json" does not exist');
        (new FixtureLoader(__DIR__.'/../Fixtures/Library/fixtures'))->load('nope.json');
    }

    public function testNamesTheMissingConfiguration(): void
    {
        $this->expectExceptionMessage('ux_showcase.fixtures_directory');
        (new FixtureLoader(null))->load('a.json');
    }
}
