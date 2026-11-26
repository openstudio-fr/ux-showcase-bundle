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

use OpenStudio\UxShowcaseBundle\Screen\Background;
use PHPUnit\Framework\TestCase;

final class BackgroundTest extends TestCase
{
    public function testAColourBecomesAnInlineStyleOnTheHtmlElement(): void
    {
        self::assertSame(
            ['style' => 'background-color: #fff', 'data-ux-showcase-background' => 'white'],
            (new Background('white', '#fff'))->toHtmlAttributes(),
        );
    }

    public function testConfiguredAttributesComeFirstAndAStyleOfTheirsIsKept(): void
    {
        $attributes = (new Background('night', '#212529', ['data-bs-theme' => 'dark', 'style' => 'color-scheme: dark;', 'data-flag' => true]))->toHtmlAttributes();

        self::assertSame('dark', $attributes['data-bs-theme']);
        self::assertSame('true', $attributes['data-flag']);
        self::assertSame('color-scheme: dark; background-color: #212529', $attributes['style']);
        self::assertSame('night', $attributes['data-ux-showcase-background']);
    }
}
