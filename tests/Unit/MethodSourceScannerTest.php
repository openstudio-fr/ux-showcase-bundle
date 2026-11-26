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

use OpenStudio\UxShowcaseBundle\Props\MethodSourceScanner;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Props\Scanned;
use PHPUnit\Framework\TestCase;

final class MethodSourceScannerTest extends TestCase
{
    public function testEveryFormOfAssignmentToAPropertyIsSeen(): void
    {
        self::assertSame(
            ['plain', 'list', 'map', 'text', 'count', 'maybe'],
            MethodSourceScanner::assignedProperties(new \ReflectionMethod(Scanned::class, 'mount')),
        );
    }

    public function testAReadOrAComparisonIsNotAnAssignment(): void
    {
        self::assertNotContains('untouched', MethodSourceScanner::assignedProperties(new \ReflectionMethod(Scanned::class, 'mount')));
    }

    public function testLiteralAllowedValuesAreRead(): void
    {
        self::assertSame(
            [
                'size' => ['s', 'm', 'l'],
                'level' => [1, 2, -3],
                'ratio' => [0.5, 1.5],
                'flag' => [true, false, null],
            ],
            MethodSourceScanner::allowedValues(new \ReflectionMethod(Scanned::class, 'options')),
        );
    }

    public function testComputedAllowedValuesAreLeftOut(): void
    {
        $allowed = MethodSourceScanner::allowedValues(new \ReflectionMethod(Scanned::class, 'options'));

        foreach (['computed', 'constant', 'keyed', 'nested'] as $name) {
            self::assertArrayNotHasKey($name, $allowed);
        }
    }
}
